import 'routes.dart';

/// Where a request for a page should actually go.
///
/// A pure function, deliberately: this is the piece that decides whether
/// somebody sees what they asked for, and it was wrong in a way nobody could
/// have caught without being able to call it — a reload on any page ended at
/// home, because the waiting room forgot where the visitor had been going.
class RouteDecision {
  const RouteDecision._();

  /// Screens somebody signed out may reach on their own.
  static const signedOut = {
    Routes.signIn,
    Routes.register,
    Routes.forgotPassword,
  };

  /// The doors somebody has already come through. A whitelist of signed-in
  /// routes would have to be edited for every new screen, and forgetting shows
  /// up as that screen silently bouncing to home — which reads as the screen
  /// being broken rather than the list being stale.
  static const entry = {
    Routes.startup,
    Routes.signIn,
    Routes.register,
    Routes.forgotPassword,
  };

  /// Null means "stay where you are".
  ///
  /// [uri] is the whole request, query and all, because where somebody was
  /// going is carried in it while the stored token is being checked.
  static String? forRequest({
    required String location,
    required Uri uri,
    required bool tokenChecked,
    required bool signedIn,
    required bool mustJoin,
    String devRoute = '',
  }) {
    if (!tokenChecked) {
      // Waiting, but carrying the destination. Without this a reload waited at
      // /startup and arrived at home.
      return location == Routes.startup
          ? null
          : '${Routes.startup}?from=${Uri.encodeComponent(uri.toString())}';
    }

    if (!signedIn) {
      return signedOut.contains(location) ? null : Routes.signIn;
    }

    if (mustJoin) {
      return location == Routes.joinTribe ? null : Routes.joinTribe;
    }

    // Debug-only: open straight onto a named screen, so a reload does not mean
    // tapping back to where you were.
    if (devRoute.isNotEmpty && location == Routes.startup) {
      return devRoute;
    }

    final intended = uri.queryParameters['from'];

    // Back to the page that was refreshed. Guarded against pointing at the
    // waiting room itself, which would be a loop — and a loop here is a page
    // that never finishes loading.
    // The path compared, not the string: startup is "/", and every path
    // begins with that — a prefix check here rejected every destination and
    // sent everybody to home regardless.
    if (location == Routes.startup && intended != null && intended.isNotEmpty) {
      final destination = Uri.tryParse(intended);

      if (destination != null && destination.path != Routes.startup) {
        return intended;
      }
    }

    return entry.contains(location) ? Routes.home : null;
  }
}
