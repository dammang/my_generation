import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/sign_in_screen.dart';
import '../features/connection/startup_screen.dart';
import '../features/home/home_screen.dart';
import '../providers/auth_provider.dart';

class Routes {
  const Routes._();

  static const String startup = '/';
  static const String signIn = '/sign-in';
  static const String home = '/home';
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

      return switch (auth) {
        // The stored token has not been checked yet. Waiting is better than
        // flashing sign-in at somebody who is already signed in.
        AuthUnknown() => location == Routes.startup ? null : Routes.startup,
        AuthSignedOut() => location == Routes.signIn ? null : Routes.signIn,
        AuthSignedIn() => location == Routes.home ? null : Routes.home,
      };
    },
    routes: [
      GoRoute(path: Routes.startup, builder: (_, _) => const StartupScreen()),
      GoRoute(path: Routes.signIn, builder: (_, _) => const SignInScreen()),
      GoRoute(path: Routes.home, builder: (_, _) => const HomeScreen()),
    ],
  );
});

/// Bridges Riverpod's auth state to GoRouter's listener contract.
class _AuthRefresh extends ChangeNotifier {
  _AuthRefresh(Ref ref) {
    ref.listen(authProvider, (_, _) => notifyListeners());
  }
}
