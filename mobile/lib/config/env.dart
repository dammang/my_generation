import 'package:flutter/foundation.dart';

/// Compile-time configuration.
///
/// Values come from `--dart-define`, so a build is pinned to an environment and
/// nothing environment-specific is committed. Defaults target a local Laravel
/// running on the developer's machine.
class Env {
  const Env._();

  /// Where the API lives.
  ///
  /// The default differs by platform because "localhost" means different things
  /// to each: an Android emulator reaches the host at 10.0.2.2, an iOS
  /// simulator shares the host's loopback, and a physical device needs the
  /// machine's LAN address. See [ApiConfig.defaultBaseUrl].
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );

  static const String appName = String.fromEnvironment(
    'APP_NAME',
    defaultValue: 'My Generation',
  );

  /// Extra network logging.
  ///
  /// Gated on the build mode rather than on the define alone: a release build
  /// that happens to carry LOG_REQUESTS would otherwise write request bodies —
  /// names, dates, the contents of a family's records — into the device log,
  /// where any other app with log access can read them.
  static bool get logRequests =>
      kDebugMode && const bool.fromEnvironment('LOG_REQUESTS');

  /// A token to start the app already signed in, for development.
  ///
  /// Ignored entirely outside debug builds — the check is on kDebugMode, not on
  /// the value being absent, so shipping a build that happens to carry one
  /// still cannot use it. Useful on a simulator, where typing credentials is
  /// slow and automated UI checks cannot type at all.
  static const String devToken = String.fromEnvironment('DEV_TOKEN', defaultValue: '');

  /// A route to open on launch, for development. Debug builds only, like
  /// [devToken] — it exists so a screen deep in the app can be reached without
  /// tapping through to it every reload.
  static const String devRoute = String.fromEnvironment('DEV_ROUTE', defaultValue: '');
}
