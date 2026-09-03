/// OAuth client identifiers for Google sign-in.
///
/// These are not secrets — they are public client identifiers, and they appear
/// in google-services.json inside every installed copy of the app. They are
/// named here because the two platforms need different ones and the failure
/// mode for getting it wrong is silent.
class GoogleOAuth {
  const GoogleOAuth._();

  /// The **web** client, despite there being no web app.
  ///
  /// This is the one that matters most and the one that is easiest to get
  /// wrong. On Android, `google_sign_in` returns an ID token only when a server
  /// client id is supplied; without it `authentication.idToken` is null and
  /// sign-in fails at the last step, after the person has already picked their
  /// account. It names the audience the token is issued *for*.
  static const String serverClientId =
      '665433621724-04nubho1rep17t6ukj86s62il5j5tpl2.apps.googleusercontent.com';

  /// The iOS client, from the Firebase project's iOS app.
  static const String iosClientId =
      '665433621724-abt3an03lccnokdumv3aa8b8ac1n42ei.apps.googleusercontent.com';

  /// The same iOS client id with its domain reversed, which is the URL scheme
  /// Google's sheet returns through. It must also be registered in
  /// ios/Runner/Info.plist or the callback lands nowhere.
  static const String iosReversedClientId =
      'com.googleusercontent.apps.665433621724-abt3an03lccnokdumv3aa8b8ac1n42ei';
}
