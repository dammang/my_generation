import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
import '../models/clan_registration.dart';
import '../models/committee.dart';
import '../models/family_branch_summary.dart';
import '../models/person_summary.dart';

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

  // ── The clan itself ──────────────────────────────────────────────────

  Future<ClanDetail> clan(String ulid) async {
    final envelope = await _api.get<Map<String, dynamic>>(
      ApiPaths.clan(ulid),
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return ClanDetail.fromJson(envelope.data!);
  }

  /// Creates the ancestor a clan descends from and records them as its
  /// starting point.
  ///
  /// Two calls, in this order, because the second cannot be made until the
  /// first has an id — and the server refuses an ancestor who is not in the
  /// clan, so creating them anywhere else would fail on the way back.
  ///
  /// Returns the new person's ulid, so the caller can open them and carry
  /// straight on adding their children.
  Future<String> startTreeWith({
    required String clanUlid,
    required String displayName,
    String? gender,
    String? birth,
  }) async {
    final created = await _api.post<Map<String, dynamic>>(
      ApiPaths.people,
      body: {
        'display_name': displayName,
        'clan_ulid': clanUlid,
        if (gender != null && gender.isNotEmpty) 'gender': gender,
        if (birth != null && birth.trim().isNotEmpty) 'birth': birth.trim(),
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    final personUlid = created.data!['ulid'] as String;

    await _api.patch<Map<String, dynamic>>(
      ApiPaths.clan(clanUlid),
      body: {'ancestor_person_ulid': personUlid},
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return personUlid;
  }

  // ── Family branches ──────────────────────────────────────────────────

  /// The named lines inside one clan.
  Future<List<FamilyBranchSummary>> branches(String clanUlid) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.familyBranches,
      query: {'clan': clanUlid, 'per_page': 50},
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map(
          (b) =>
              FamilyBranchSummary.fromJson((b as Map).cast<String, dynamic>()),
        )
        .toList(growable: false);
  }

  /// Creates a line and says where it starts.
  ///
  /// Returns how many people the branch gained, which is the only visible
  /// consequence: the server counts everybody descended from the ancestor and
  /// places those who belong to no line yet, and that is what gives them a
  /// generation.
  Future<int> createBranch({
    required String tribeUlid,
    required String clanUlid,
    required String name,
    String? ancestorUlid,
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.familyBranches,
      body: {
        'tribe_ulid': tribeUlid,
        'clan_ulid': clanUlid,
        'name': name,
        'ancestor_person_ulid': ?ancestorUlid,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return envelope.meta['people_placed'] as int? ?? 0;
  }

  Future<int> setBranchAncestor({
    required String branchUlid,
    required String ancestorUlid,
  }) async {
    final envelope = await _api.patch<Map<String, dynamic>>(
      ApiPaths.familyBranch(branchUlid),
      body: {'ancestor_person_ulid': ancestorUlid},
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return envelope.meta['people_placed'] as int? ?? 0;
  }

  /// People already in the clan, for choosing which of them a line starts at.
  Future<List<PersonSummary>> peopleIn(
    String clanUlid, {
    String? search,
  }) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.people,
      query: {
        'clan': clanUlid,
        if (search != null && search.trim().isNotEmpty) 'q': search.trim(),
        'per_page': 30,
      },
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((p) => PersonSummary.fromJson((p as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  // ── Generations ──────────────────────────────────────────────────────

  /// Every generation label usable in one tribe, its clans' own included.
  Future<List<GenerationLabel>> generations(String tribeUlid) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.generations,
      query: {'tribe': tribeUlid},
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map(
          (g) => GenerationLabel.fromJson((g as Map).cast<String, dynamic>()),
        )
        .toList(growable: false);
  }

  /// A label a clan names for itself.
  ///
  /// Sent with the clan, so it belongs to that clan rather than to the whole
  /// tribe — and so a clan admin is allowed to make it.
  Future<GenerationLabel> createGeneration({
    required String tribeUlid,
    required String clanUlid,
    required int number,
    String? name,
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.generations,
      body: {
        'tribe_ulid': tribeUlid,
        'clan_ulid': clanUlid,
        'generation_number': number,
        'generation_name': ?name,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return GenerationLabel.fromJson(envelope.data!);
  }

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
