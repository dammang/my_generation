import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';

/// Follows a notification's `route` to where it actually points.
///
/// The server writes a deep link into every push it sends — `toFcm()['data']`
/// on ChangeRequestAwaitingReview and MembershipDecided both do this — and
/// until now nothing on this side ever read it. A tap opened the app to
/// wherever it happened to land, never to the thing the notification was
/// about, which made the deep link a promise the client never kept.
///
/// A tap can arrive by two different paths, and they are not the same code
/// path in the SDK:
/// - the app was already running, backgrounded — a stream of taps.
/// - the app was not running at all, and the tap is what launched it — only
///   one message is ever seen this way, and only once, at startup.
///
/// Missing the second case is the one that is easy to miss testing by hand:
/// backgrounding the app and tapping a notification exercises the first path
/// only. A cold start needs the app closed from the app switcher first.
class PushNavigationService {
  PushNavigationService(
    this._router, {
    FirebaseMessaging? messaging,
    Stream<RemoteMessage>? openedAppTaps,
  }) : _messaging = messaging,
       _openedAppTapsOverride = openedAppTaps;

  final GoRouter _router;

  // Both real defaults touch Firebase statics that throw without
  // Firebase.initializeApp() having run — FirebaseMessaging.instance needs a
  // default app, and the static onMessageOpenedApp getter goes through it
  // too. Resolving them lazily, inside start()'s try block, is what lets a
  // widget test supply openedAppTaps directly and never touch either one.
  final FirebaseMessaging? _messaging;
  final Stream<RemoteMessage>? _openedAppTapsOverride;

  Future<void> start() async {
    try {
      final messaging = _messaging ?? FirebaseMessaging.instance;
      final initial = await messaging.getInitialMessage();

      if (initial != null) _followRoute(initial);
    } catch (error) {
      // Not fatal to app startup — the notification that launched the app
      // failing to deep-link is a missed convenience, not a broken launch.
      if (kDebugMode) debugPrint('Could not read the launching notification: $error');
    }

    final openedAppTaps = _openedAppTapsOverride ?? FirebaseMessaging.onMessageOpenedApp;

    openedAppTaps.listen(_followRoute);
  }

  void _followRoute(RemoteMessage message) {
    final route = message.data['route'];

    if (route is! String || route.isEmpty) return;

    // Goes through the same GoRouter instance the rest of the app navigates
    // with, so its redirect logic still applies: a signed-out person tapping
    // a stale notification is sent to sign in, not straight to a screen that
    // requires a session.
    _router.go(route);
  }
}
