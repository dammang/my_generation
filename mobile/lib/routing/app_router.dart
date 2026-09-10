import 'package:firebase_analytics/firebase_analytics.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

import '../config/env.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/forgot_password_screen.dart';
import '../features/auth/register_screen.dart';
import '../features/auth/sign_in_screen.dart';
import '../features/clans/view/administer_screen.dart';
import '../features/clans/view/administered_scopes_screen.dart';
import '../features/clans/view/clan_registrations_screen.dart';
import '../features/clans/view/start_clan_screen.dart';
import '../features/connection/startup_screen.dart';
import '../features/home/home_screen.dart';
import '../features/onboarding/claim_profile_screen.dart';
import '../features/clans/view/members_screen.dart';
import '../features/clans/view/membership_requests_screen.dart';
import '../features/onboarding/join_clan_screen.dart';
import '../features/onboarding/join_screen.dart';
import '../features/person/view/person_screen.dart';
import '../features/profile/view/profile_screen.dart';
import '../features/review/view/review_queue_screen.dart';
import '../features/search/view/person_search_screen.dart';
import '../features/shell/view/app_shell.dart';
import '../features/sync/view/pending_changes_screen.dart';
import '../features/tree/view/my_lineage_screen.dart';
import '../features/tree/view/tree_screen.dart';
import '../providers/auth_provider.dart';
import '../providers/onboarding_provider.dart';

import 'route_decision.dart';
import 'routes.dart';

export 'routes.dart';

/// What sends screen views to Firebase.
///
/// AnalyticsService itself only ever offers .screen() and .milestone() —
/// nothing called either, on any screen, anywhere. This observer is what makes
/// navigation actually reach Firebase; without it the service exists and
/// analytics shows nothing, which is exactly what was happening.
///
/// A fresh list per call, because a NavigatorObserver belongs to one Navigator
/// and the shell has one per branch — and every navigator needs its own or the
/// tabs report nothing. A root observer alone would only ever see the sign-in
/// screens and the person pages, which is the smaller half of the app.
///
/// Built inside a try because FirebaseAnalytics.instance throws outright
/// without Firebase.initializeApp() — the same trap PushNavigationService
/// documents. Letting that escape would make the router, and so the entire
/// app, impossible to build in a widget test. Silent because the one case
/// worth hearing about, Firebase failing to start on a real phone, is already
/// reported where it happens, in main's _startFirebase.
List<NavigatorObserver> _analyticsObservers() {
  try {
    return [FirebaseAnalyticsObserver(analytics: FirebaseAnalytics.instance)];
  } catch (_) {
    return const [];
  }
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
    observers: _analyticsObservers(),
    redirect: (context, state) {
      final auth = ref.read(authProvider);

      return RouteDecision.forRequest(
        location: state.matchedLocation,
        uri: state.uri,
        tokenChecked: auth is! AuthUnknown,
        signedIn: auth is AuthSignedIn,
        // A cached future: it never blocks navigation, and once they have
        // asked to join, the redirect stops.
        mustJoin: ref.read(needsOnboardingProvider).value ?? false,
        devRoute: kDebugMode ? Env.devRoute : '',
      );
    },
    routes: [
      // Outside the shell, and so without a bottom bar: the doors into the app,
      // and the detail pages that open on top of whichever section you were in.
      // A person opened from the tree covers the bar, the way a detail page
      // should — and keeping it at the root is also what lets a notification
      // deep-link to /person/… land identically from any tab.
      GoRoute(path: Routes.startup, builder: (_, _) => const StartupScreen()),
      GoRoute(path: Routes.signIn, builder: (_, _) => const SignInScreen()),
      GoRoute(path: Routes.register, builder: (_, _) => const RegisterScreen()),
      GoRoute(
        path: Routes.forgotPassword,
        builder: (_, _) => const ForgotPasswordScreen(),
      ),
      GoRoute(path: Routes.joinTribe, builder: (_, _) => const JoinScreen()),
      GoRoute(path: Routes.joinClan, builder: (_, _) => const JoinClanScreen()),
      GoRoute(path: Routes.members, builder: (_, _) => const MembersScreen()),
      GoRoute(
        path: Routes.joinRequests,
        builder: (_, _) => const MembershipRequestsScreen(),
      ),
      GoRoute(
        path: Routes.claimProfile,
        builder: (_, _) => const ClaimProfileScreen(),
      ),
      GoRoute(
        path: '${Routes.person}/:ulid',
        builder: (_, state) => PersonScreen(
          ulid: state.pathParameters['ulid']!,
          initialTab: PersonScreen.tabIndexFor(
            state.uri.queryParameters['tab'],
          ),
        ),
      ),

      // The five sections. indexedStack rather than plain branches because each
      // one has to survive being tabbed away from: the tree in particular is
      // expensive to lay out and infuriating to lose your place in.
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => AppShell(shell: shell),
        branches: [
          StatefulShellBranch(
            observers: _analyticsObservers(),
            routes: [
              GoRoute(path: Routes.home, builder: (_, _) => const HomeScreen()),
            ],
          ),
          StatefulShellBranch(
            observers: _analyticsObservers(),
            routes: [
              GoRoute(
                path: Routes.tree,
                builder: (_, state) => TreeScreen(
                  initialUlid: state.uri.queryParameters['person'],
                ),
                routes: [
                  GoRoute(
                    path: 'search',
                    builder: (_, _) => const PersonSearchScreen(),
                  ),
                  GoRoute(
                    path: 'my-lineage',
                    builder: (_, _) => const MyLineageScreen(),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            observers: _analyticsObservers(),
            routes: [
              GoRoute(
                path: Routes.contributions,
                builder: (_, state) => ReviewQueueScreen(
                  initialTab: ReviewQueueScreen.tabIndexFor(
                    state.uri.queryParameters['tab'],
                  ),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            observers: _analyticsObservers(),
            routes: [
              GoRoute(
                path: Routes.pendingChanges,
                builder: (_, _) => const PendingChangesScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            observers: _analyticsObservers(),
            routes: [
              GoRoute(
                path: Routes.profile,
                builder: (_, _) => const ProfileScreen(),
                routes: [
                  GoRoute(
                    path: 'clans',
                    builder: (_, _) => const ClanRegistrationsScreen(),
                    routes: [
                      GoRoute(
                        path: 'new',
                        builder: (_, _) => const StartClanScreen(),
                      ),
                    ],
                  ),
                  GoRoute(
                    path: 'committee',
                    builder: (_, _) => const AdministeredScopesScreen(),
                    routes: [
                      GoRoute(
                        path: ':type/:ulid',
                        builder: (_, state) => AdministerScreen(
                          scopeType: state.pathParameters['type']!,
                          scopeUlid: state.pathParameters['ulid']!,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    ],
  );
});

/// Bridges Riverpod's auth state to GoRouter's listener contract.
class _AuthRefresh extends ChangeNotifier {
  _AuthRefresh(Ref ref) {
    ref.listen(authProvider, (_, _) => notifyListeners());
    // Asking to join resolves the onboarding question, and the router has to
    // hear about it or the person stays on the join screen.
    ref.listen(needsOnboardingProvider, (_, _) => notifyListeners());
  }
}
