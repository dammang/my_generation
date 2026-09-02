import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
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
      query: {if (search != null && search.isNotEmpty) 'q': search, 'per_page': 50},
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((t) => TribeSummary.fromJson((t as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  Future<List<Membership>> myMemberships() async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.memberships,
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((m) => Membership.fromJson((m as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  Future<Membership> requestMembership({
    required String scopeType,
    required String scopeUlid,
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.memberships,
      body: {'scope_type': scopeType, 'scope_ulid': scopeUlid},
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
        if (statement != null && statement.isNotEmpty) 'relationship_statement': statement,
        if (evidence != null && evidence.isNotEmpty) 'evidence': evidence,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return ProfileClaim.fromJson(envelope.data!);
  }
}
