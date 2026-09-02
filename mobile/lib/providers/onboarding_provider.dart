import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/membership.dart';
import '../models/profile_claim.dart';
import '../models/tribe_summary.dart';
import '../repositories/onboarding_repository.dart';
import 'app_providers.dart';
import 'auth_provider.dart';

final onboardingRepositoryProvider = Provider<OnboardingRepository>(
  (ref) => OnboardingRepository(ref.watch(apiClientProvider)),
);

/// Tribes matching a search, or the first page when the search is empty.
final tribesProvider = FutureProvider.family<List<TribeSummary>, String>(
  (ref, search) => ref.watch(onboardingRepositoryProvider).tribes(search: search),
);

final myMembershipsProvider = FutureProvider<List<Membership>>(
  (ref) => ref.watch(onboardingRepositoryProvider).myMemberships(),
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
