import 'package:flutter/foundation.dart';

import '../config/env.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/forgot_password_screen.dart';
import '../features/auth/register_screen.dart';
import '../features/auth/sign_in_screen.dart';
import '../features/connection/startup_screen.dart';
import '../features/home/home_screen.dart';
import '../features/onboarding/claim_profile_screen.dart';
import '../features/onboarding/join_tribe_screen.dart';
import '../features/person/view/person_screen.dart';
import '../features/review/view/review_queue_screen.dart';
import '../features/sync/view/pending_changes_screen.dart';
import '../features/tree/view/tree_screen.dart';
import '../providers/auth_provider.dart';
import '../providers/onboarding_provider.dart';

class Routes {
  const Routes._();

  static const String startup = '/';
  static const String signIn = '/sign-in';
  static const String register = '/register';
  static const String forgotPassword = '/forgot-password';
  static const String joinTribe = '/join';
  static const String claimProfile = '/claim';
  static const String home = '/home';
  static const String tree = '/tree';

  /// A profile is addressable so a link to one survives being shared — the
  /// ulid is the public identifier precisely so it can appear in a URL.
  static const String contributions = '/contributions';
  static const String pendingChanges = '/pending';

  static const String person = '/person';

  static String personPath(String ulid) => '$person/$ulid';
}

/// Routing follows the auth state rather than the other way round.
///
/// Screens never decide where somebody belongs — a redirect driven by one
/// source of truth is the only way to avoid two screens disagreeing about
/// whether a person is signed in.
final routerProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: Routes.startup,
    refreshListenable: _AuthRefresh(ref),
    redirect: (context, state) {
      final auth = ref.read(authProvider);
      final location = state.matchedLocation;

      // Screens a signed-out person may reach on their own.
      const signedOutRoutes = {Routes.signIn, Routes.register, Routes.forgotPassword};

      return switch (auth) {
        // The stored token has not been checked yet. Waiting is better than
        // flashing sign-in at somebody who is already signed in.
        AuthUnknown() => location == Routes.startup ? null : Routes.startup,

        AuthSignedOut() => signedOutRoutes.contains(location) ? null : Routes.signIn,

        // Somebody with no membership can see almost nothing, so they are asked
        // to join before being shown an empty home. The check is a cached
        // future: it never blocks navigation, and once they have asked, the
        // redirect stops.
        AuthSignedIn() => _afterSignIn(ref, location),
      };
    },
    routes: [
      GoRoute(path: Routes.startup, builder: (_, _) => const StartupScreen()),
      GoRoute(path: Routes.signIn, builder: (_, _) => const SignInScreen()),
      GoRoute(path: Routes.register, builder: (_, _) => const RegisterScreen()),
      GoRoute(path: Routes.forgotPassword, builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(path: Routes.joinTribe, builder: (_, _) => const JoinTribeScreen()),
      GoRoute(path: Routes.claimProfile, builder: (_, _) => const ClaimProfileScreen()),
      GoRoute(path: Routes.home, builder: (_, _) => const HomeScreen()),
      GoRoute(
        path: Routes.pendingChanges,
        builder: (_, _) => const PendingChangesScreen(),
      ),
      GoRoute(
        path: Routes.contributions,
        builder: (_, state) => ReviewQueueScreen(
          initialTab: ReviewQueueScreen.tabIndexFor(state.uri.queryParameters['tab']),
        ),
      ),
      GoRoute(
        path: '${Routes.person}/:ulid',
        builder: (_, state) => PersonScreen(
          ulid: state.pathParameters['ulid']!,
          initialTab: PersonScreen.tabIndexFor(state.uri.queryParameters['tab']),
        ),
      ),
      GoRoute(
        path: Routes.tree,
        builder: (_, state) => TreeScreen(initialUlid: state.uri.queryParameters['person']),
      ),
    ],
  );
});

/// Where a signed-in person belongs.
///
/// Claiming a profile is optional and reachable from the profile screen, so it
/// is never forced here; only the tribe question is worth interrupting for.
String? _afterSignIn(Ref ref, String location) {
  final needsOnboarding = ref.read(needsOnboardingProvider);

  // While the answer is still loading, stay put rather than bouncing somebody
  // between two screens on a slow connection.
  final mustJoin = needsOnboarding.value ?? false;

  if (mustJoin) {
    return location == Routes.joinTribe ? null : Routes.joinTribe;
  }

  // Debug-only: open straight onto a named screen, so a reload does not mean
  // tapping back to where you were.
  if (kDebugMode && Env.devRoute.isNotEmpty && location == Routes.startup) {
    return Env.devRoute;
  }

  // Anywhere except the doors somebody has already come through. A whitelist
  // of signed-in routes has to be edited for every new screen, and forgetting
  // shows up as that screen silently bouncing to home — which reads as the
  // screen being broken rather than the list being stale.
  const entryRoutes = {Routes.startup, Routes.signIn, Routes.register, Routes.forgotPassword};

  return entryRoutes.contains(location) ? Routes.home : null;
}

/// Bridges Riverpod's auth state to GoRouter's listener contract.
class _AuthRefresh extends ChangeNotifier {
  _AuthRefresh(Ref ref) {
    ref.listen(authProvider, (_, _) => notifyListeners());
    // Asking to join resolves the onboarding question, and the router has to
    // hear about it or the person stays on the join screen.
    ref.listen(needsOnboardingProvider, (_, _) => notifyListeners());
  }
}
