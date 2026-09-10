import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/clan_summary.dart';
import '../models/committee.dart';
import '../models/membership.dart';
import '../models/profile_claim.dart';
import '../models/tribe_summary.dart';
import '../repositories/onboarding_repository.dart';
import 'app_providers.dart';
import 'auth_provider.dart';
import 'clan_provider.dart';

final onboardingRepositoryProvider = Provider<OnboardingRepository>(
  (ref) => OnboardingRepository(ref.watch(apiClientProvider)),
);

/// Tribes matching a search, or the first page when the search is empty.
final tribesProvider = FutureProvider.family<List<TribeSummary>, String>(
  (ref, search) =>
      ref.watch(onboardingRepositoryProvider).tribes(search: search),
);

/// Clans matching a search, or the first page when the search is empty.
final joinableClansProvider = FutureProvider.family<List<ClanSummary>, String>(
  (ref, search) =>
      ref.watch(onboardingRepositoryProvider).clans(search: search),
);

final myMembershipsProvider = FutureProvider<List<Membership>>(
  (ref) => ref.watch(onboardingRepositoryProvider).myMemberships(),
);

/// Everybody waiting to be let into a family this account runs.
///
/// Gathered across the scopes rather than asked for one at a time: somebody
/// who runs two clans should see one queue, not have to remember to check the
/// second. Failures on one scope are dropped rather than emptying the list —
/// a queue that vanishes because one request timed out reads as "nobody is
/// waiting", which is the one answer it must never give by accident.
final pendingMembershipsProvider =
    FutureProvider<List<({AdministeredScope scope, Membership membership})>>((
      ref,
    ) async {
      final scopes = await ref.watch(administeredScopesProvider.future);

      final queues = await Future.wait(
        scopes.map((scope) async {
          try {
            final rows = await ref
                .watch(onboardingRepositoryProvider)
                .pendingFor(
                  scopeType: scope.scopeType,
                  scopeUlid: scope.scopeUlid,
                );

            return rows.map((m) => (scope: scope, membership: m)).toList();
          } catch (_) {
            return <({AdministeredScope scope, Membership membership})>[];
          }
        }),
      );

      return queues.expand((queue) => queue).toList(growable: false);
    });

/// The members of one scope, for whoever administers it.
final scopeMembersProvider =
    FutureProvider.family<List<Membership>, ({String type, String ulid})>(
      (ref, scope) => ref
          .watch(onboardingRepositoryProvider)
          .membersOf(scopeType: scope.type, scopeUlid: scope.ulid),
    );

final myClaimsProvider = FutureProvider<List<ProfileClaim>>(
  (ref) => ref.watch(onboardingRepositoryProvider).myClaims(),
);

/// Whether the joining flow still has something to ask for.
///
/// Somebody with no membership anywhere can see almost nothing, so sending them
/// straight to an empty home would be a worse first impression than asking one
/// question. Once they have asked to join, onboarding is done — approval is
/// somebody else's to give, and waiting on a screen for it helps nobody.
final needsOnboardingProvider = FutureProvider<bool>((ref) async {
  final auth = ref.watch(authProvider);

  if (auth is! AuthSignedIn) return false;

  // An administrator already reaches everything; asking them to join a tribe
  // would be a question with no purpose behind it.
  if (auth.user.isSuperAdmin) return false;

  if (auth.user.tribeIds.isNotEmpty) return false;

  final memberships = await ref.watch(myMembershipsProvider.future);

  return memberships.isEmpty;
});
