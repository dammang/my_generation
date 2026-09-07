import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/clan_registration.dart';
import '../models/committee.dart';
import '../models/family_branch_summary.dart';
import '../models/person_summary.dart';
import '../repositories/clan_repository.dart';
import 'app_providers.dart';

/// A scope addressed the way the API addresses one: by what it is, not by an
/// id of its own. A record so the family key compares by value.
typedef ScopeRef = ({String type, String ulid});

final clanRepositoryProvider = Provider<ClanRepository>(
  (ref) => ClanRepository(ref.watch(apiClientProvider)),
);

/// Requests to start a clan: the caller's own, and any waiting on them.
final clanRegistrationsProvider = FutureProvider<List<ClanRegistration>>(
  (ref) => ref.watch(clanRepositoryProvider).registrations(),
);

/// Where this account may appoint people. Empty for almost everybody, which
/// is why the committee is offered only when this has something in it.
final administeredScopesProvider = FutureProvider<List<AdministeredScope>>(
  (ref) => ref.watch(clanRepositoryProvider).administeredScopes(),
);

/// One clan, for the page that administers it.
final clanProvider = FutureProvider.family<ClanDetail, String>(
  (ref, ulid) => ref.watch(clanRepositoryProvider).clan(ulid),
);

/// The named lines inside one clan.
final clanBranchesProvider =
    FutureProvider.family<List<FamilyBranchSummary>, String>(
      (ref, clanUlid) => ref.watch(clanRepositoryProvider).branches(clanUlid),
    );

/// People already in the clan, narrowed by what has been typed.
final clanPeopleProvider =
    FutureProvider.family<List<PersonSummary>, ({String clan, String query})>(
      (ref, args) => ref
          .watch(clanRepositoryProvider)
          .peopleIn(args.clan, search: args.query),
    );

/// Generation labels usable in one tribe.
final generationsProvider =
    FutureProvider.family<List<GenerationLabel>, String>(
      (ref, tribeUlid) =>
          ref.watch(clanRepositoryProvider).generations(tribeUlid),
    );

final committeeProvider = FutureProvider.family<List<Appointment>, ScopeRef>(
  (ref, scope) => ref
      .watch(clanRepositoryProvider)
      .appointments(scopeType: scope.type, scopeUlid: scope.ulid),
);

/// Who could be appointed, narrowed by what has been typed.
///
/// The search is part of the key so an in-flight request for an older query
/// cannot land after a newer one and overwrite it.
final committeeCandidatesProvider =
    FutureProvider.family<
      List<CommitteeCandidate>,
      ({ScopeRef scope, String query})
    >(
      (ref, args) => ref
          .watch(clanRepositoryProvider)
          .candidates(
            scopeType: args.scope.type,
            scopeUlid: args.scope.ulid,
            search: args.query,
          ),
    );
