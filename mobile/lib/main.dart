import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_crashlytics/firebase_crashlytics.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'firebase_options.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await _startFirebase();

  runApp(const ProviderScope(child: MyGenerationApp()));
}

/// Brings Firebase up, and never lets its absence stop the app.
///
/// A phone with no network on first launch, a misconfigured build, a Firebase
/// outage — none of those should present as an app that will not open. The
/// archive already on the device is readable without any of this, and the
/// sign-in screen can say what is wrong far better than a blank screen can.
Future<void> _startFirebase() async {
  try {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
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
}
