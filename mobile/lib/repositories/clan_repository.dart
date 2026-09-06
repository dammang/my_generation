import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
import '../models/clan_registration.dart';
import '../models/committee.dart';

/// Starting a clan, and running one once it exists.
///
/// Both live here because they are one story from the user's side: you ask for
/// a clan, somebody approves it, and then you have a committee to appoint.
class ClanRepository {
  ClanRepository(this._api);

  final ApiClient _api;

  /// The caller's own requests, plus anything they are able to decide.
  ///
  /// One list rather than two calls: the server already separates them with
  /// `can_decide`, and somebody who runs a tribe has usually also asked for
  /// something themselves.
  Future<List<ClanRegistration>> registrations() async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.clanRegistrations,
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map(
          (r) => ClanRegistration.fromJson((r as Map).cast<String, dynamic>()),
        )
        .toList(growable: false);
  }

  Future<ClanRegistration> requestClan({
    required String tribeUlid,
    required String name,
    String? nativeName,
    String? statement,
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.clanRegistrations,
      body: {
        'tribe_ulid': tribeUlid,
        'name': name,
        if (nativeName != null && nativeName.isNotEmpty)
          'native_name': nativeName,
        if (statement != null && statement.isNotEmpty) 'statement': statement,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return ClanRegistration.fromJson(envelope.data!);
  }

  Future<void> approve(String ulid, {String? note}) =>
      _decide(ApiPaths.approveClanRegistration(ulid), note);

  Future<void> reject(String ulid, {String? note}) =>
      _decide(ApiPaths.rejectClanRegistration(ulid), note);

  /// Changing your mind, which is not the same as being refused.
  Future<void> withdraw(String ulid) =>
      _decide(ApiPaths.withdrawClanRegistration(ulid), null);

  Future<void> _decide(String path, String? note) => _api.post<void>(
    path,
    body: {if (note != null && note.isNotEmpty) 'note': note},
    parse: (_) {},
  );

  // ── The committee ────────────────────────────────────────────────────

  /// Where this account may appoint, and what it may hand out in each.
  Future<List<AdministeredScope>> administeredScopes() async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.administeredScopes,
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map(
          (s) => AdministeredScope.fromJson((s as Map).cast<String, dynamic>()),
        )
        .toList(growable: false);
  }

  Future<List<Appointment>> appointments({
    required String scopeType,
    required String scopeUlid,
  }) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.scopeRoles,
      query: {'scope_type': scopeType, 'scope_ulid': scopeUlid},
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((a) => Appointment.fromJson((a as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  Future<List<CommitteeCandidate>> candidates({
    required String scopeType,
    required String scopeUlid,
    String? search,
  }) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.committeeCandidates,
      query: {
        'scope_type': scopeType,
        'scope_ulid': scopeUlid,
        if (search != null && search.trim().isNotEmpty) 'q': search.trim(),
      },
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map(
          (c) =>
              CommitteeCandidate.fromJson((c as Map).cast<String, dynamic>()),
        )
        .toList(growable: false);
  }

  Future<void> appoint({
    required String scopeType,
    required String scopeUlid,
    required String userUlid,
    required String role,
  }) => _api.post<void>(
    ApiPaths.scopeRoles,
    body: {
      'scope_type': scopeType,
      'scope_ulid': scopeUlid,
      'user_ulid': userUlid,
      'role': role,
    },
    parse: (_) {},
  );

  /// The same four fields the grant was made with: an appointment is
  /// identified by who, what and where, not by an id of its own.
  Future<void> revoke({
    required String scopeType,
    required String scopeUlid,
    required String userUlid,
    required String role,
  }) => _api.delete(
    ApiPaths.scopeRoles,
    body: {
      'scope_type': scopeType,
      'scope_ulid': scopeUlid,
      'user_ulid': userUlid,
      'role': role,
    },
  );
}
