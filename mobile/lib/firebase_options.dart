// Firebase project configuration, per platform.
//
// These values are client identifiers, not secrets: they ship inside every
// installed copy of the app and anybody can read them out of an APK. What
// actually protects the project is the restriction attached to each key —
// package name plus signing certificate on Android, bundle id on iOS — set in
// the Google Cloud console. Treating them as secrets while leaving the
// restrictions unset would be protecting the wrong thing.

import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb;

class DefaultFirebaseOptions {
  const DefaultFirebaseOptions._();

  static FirebaseOptions get currentPlatform {
    if (kIsWeb) return web;

    return switch (defaultTargetPlatform) {
      TargetPlatform.android => android,
      TargetPlatform.iOS => ios,
      _ => throw UnsupportedError(
          'My Generation has no Firebase configuration for $defaultTargetPlatform.',
        ),
    };
  }

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyAUUnbgcXEa_mStyGeJhtVQkKGJ-rnsafA',
    appId: '1:665433621724:android:f04a028e4671d5105b2019',
    messagingSenderId: '665433621724',
    projectId: 'my-generation-76b6c',
    storageBucket: 'my-generation-76b6c.firebasestorage.app',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyAnfgV2_7LKhlcubGLACcfRUcoqd5a0XjI',
    appId: '1:665433621724:ios:687b437cea9019675b2019',
    messagingSenderId: '665433621724',
    projectId: 'my-generation-76b6c',
    storageBucket: 'my-generation-76b6c.firebasestorage.app',
    iosBundleId: 'com.khanggui',
  );

  /// Kept for the day a web client exists. This project has no `web/`
  /// directory, so nothing reads this yet — it is here so the configuration
  /// lives in one place rather than being hunted for later.
  static const FirebaseOptions web = FirebaseOptions(
    apiKey: 'AIzaSyD-XIh1AgjnDZ29hhM-GnPIZevBb9GdOdY',
    appId: '1:665433621724:web:8ae38c4e500a49e05b2019',
    messagingSenderId: '665433621724',
    projectId: 'my-generation-76b6c',
    authDomain: 'my-generation-76b6c.firebaseapp.com',
    storageBucket: 'my-generation-76b6c.firebasestorage.app',
    measurementId: 'G-R2K3JNP2L1',
  );
}
