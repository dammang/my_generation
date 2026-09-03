import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'firebase_options.dart';
import 'routing/app_router.dart';
import 'services/push_navigation_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Built explicitly, rather than letting ProviderScope create one implicitly,
  // because PushNavigationService needs the same GoRouter instance the app
  // renders with — reading routerProvider from this container is what lets a
  // notification tap navigate through the app's actual router, redirect logic
  // and all, instead of a second one built just for this purpose.
  final container = ProviderContainer();

  await _startFirebase(container);

  runApp(UncontrolledProviderScope(container: container, child: const MyGenerationApp()));
}

/// Brings Firebase up, and never lets its absence stop the app.
///
/// A phone with no network on first launch, a misconfigured build, a Firebase
/// outage — none of those should present as an app that will not open. The
/// archive already on the device is readable without any of this, and the
/// sign-in screen can say what is wrong far better than a blank screen can.
Future<void> _startFirebase(ProviderContainer container) async {
  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  } catch (error, stack) {
    debugPrint('Firebase did not start: $error');
    debugPrintStack(stackTrace: stack);

    return;
  }

  // Crashes in release only. In debug the console is more useful than a report,
  // and filling Crashlytics with a developer's own broken builds hides the
  // reports that came from somebody's actual phone.
  final crashlytics = FirebaseCrashlytics.instance;

  await crashlytics.setCrashlyticsCollectionEnabled(!kDebugMode);

  // Framework errors would otherwise only be printed. This is what turns "it
  // crashed on my mother's phone and I don't know why" into a stack trace.
  FlutterError.onError = (details) {
    FlutterError.presentError(details);
    crashlytics.recordFlutterFatalError(details);
  };

  // Errors that escape the framework entirely — an unawaited future, a platform
  // channel failing — reach here and nowhere else.
  PlatformDispatcher.instance.onError = (error, stack) {
    crashlytics.recordError(error, stack, fatal: true);

    return true;
  };

  // Started here, once, rather than after sign-in: the message that cold-
  // started the app is available exactly once, at the very first read of
  // getInitialMessage(), and only for a moment. Waiting until later in the
  // app's life to ask means asking after the answer already came and went.
  await PushNavigationService(container.read(routerProvider)).start();
}
