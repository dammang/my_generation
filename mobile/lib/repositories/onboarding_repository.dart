import 'dart:typed_data';

import 'package:dio/dio.dart';
import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
import '../models/clan_summary.dart';
import '../models/membership.dart';
import '../models/person_summary.dart';
import '../models/profile_claim.dart';
import '../models/tribe_summary.dart';

/// Everything the joining flow needs: finding a tribe, asking to belong, and
/// asking to be recognised as a person already in the archive.
class OnboardingRepository {
  OnboardingRepository(this._api);

  final ApiClient _api;

  Future<List<TribeSummary>> tribes({String? search}) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.tribes,
      query: {
        if (search != null && search.isNotEmpty) 'q': search,
        'per_page': 50,
      },
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((t) => TribeSummary.fromJson((t as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  /// Clans to ask to join. Filtered by the server to what this account may
  /// know exists, so an unapproved reader sees the public ones and no more.
  Future<List<ClanSummary>> clans({String? search}) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.clans,
      query: {if (search != null && search.isNotEmpty) 'q': search},
      parse: (data) => (data as List),
    );

    return envelope.data!
        .whereType<Map>()
        .map((row) => ClanSummary.fromJson(row.cast<String, dynamic>()))
        .toList(growable: false);
  }

  /// Who is waiting to be let into one scope.
  ///
  /// Asked for per scope because that is what the server authorises against:
  /// a reviewer sees the queue for the families they run and nothing else.
  Future<List<Membership>> pendingFor({
    required String scopeType,
    required String scopeUlid,
  }) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.scopeMembers,
      query: {
        'scope_type': scopeType,
        'scope_ulid': scopeUlid,
        'status': 'pending',
      },
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((m) => Membership.fromJson((m as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  Future<void> decideMembership(String ulid, {required bool approve}) =>
      _api.post<void>(
        approve
            ? ApiPaths.approveMembership(ulid)
            : ApiPaths.rejectMembership(ulid),
        body: const {},
        parse: (_) {},
      );

  Future<List<Membership>> myMemberships() async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.memberships,
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((m) => Membership.fromJson((m as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  /// Asking to join.
  ///
  /// The answers are required for a clan and ignored for a tribe — a family
  /// asks who your parents were, a tribe does not — but the decision is the
  /// server's, not this method's.
  Future<Membership> requestMembership({
    required String scopeType,
    required String scopeUlid,
    Map<String, String?> answers = const {},
    Uint8List? photoBytes,
    String? photoName,
  }) async {
    final fields = {
      'scope_type': scopeType,
      'scope_ulid': scopeUlid,
      for (final entry in answers.entries)
        if (entry.value != null && entry.value!.trim().isNotEmpty)
          entry.key: entry.value!.trim(),
    };

    // Bytes, not a path. On the web there is no filesystem to read a path
    // from — MultipartFile.fromFile throws there — and the form went quiet
    // rather than sending anything. Bytes work on both.
    //
    // Multipart only when there is a photograph: a plain map is easier to read
    // in a log and is what the tribe flow has always sent.
    final body = photoBytes == null
        ? fields
        : FormData.fromMap({
            ...fields,
            'photo': MultipartFile.fromBytes(
              photoBytes,
              filename: photoName ?? 'selfie.jpg',
            ),
          });

    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.memberships,
      body: body,
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return Membership.fromJson(envelope.data!);
  }

  /// Searching for oneself in the archive.
  ///
  /// Returns only people this account may already see, so the search cannot be
  /// used to discover who exists in a family the searcher has no part in.
  Future<List<PersonSummary>> searchPeople(String query) async {
    if (query.trim().length < 2) return const [];

    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.people,
      query: {'q': query.trim(), 'living': true, 'per_page': 20},
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((p) => PersonSummary.fromJson((p as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  Future<List<ProfileClaim>> myClaims() async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.profileClaims,
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((c) => ProfileClaim.fromJson((c as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  Future<ProfileClaim> claimProfile({
    required String personUlid,
    String? statement,
    String? evidence,
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.profileClaims,
      body: {
        'person_ulid': personUlid,
        if (statement != null && statement.isNotEmpty)
          'relationship_statement': statement,
        if (evidence != null && evidence.isNotEmpty) 'evidence': evidence,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return ProfileClaim.fromJson(envelope.data!);
  }
}
