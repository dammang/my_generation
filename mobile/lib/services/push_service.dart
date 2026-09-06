import 'dart:io' show Platform;

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../core/constants/api_paths.dart';
import '../core/errors/api_exception.dart';
import '../core/network/api_client.dart';

/// Registering this device to receive notifications, and unregistering it.
///
/// Permission is asked for at the moment it makes sense — after somebody is
/// signed in and has something to be notified about — not on first launch,
/// when the answer is reflexively no and iOS never asks again.
class PushService {
  PushService(this._api, {FirebaseMessaging? messaging})
    : _messaging = messaging ?? FirebaseMessaging.instance;

  final ApiClient _api;
  final FirebaseMessaging _messaging;

  /// Whether push is set up on this platform at all.
  ///
  /// The web build has no service worker and no VAPID key, so Firebase
  /// Messaging cannot produce a token there. Worse than not working: getToken
  /// does not fail, it never returns — and an await that never completes is
  /// not something a try/catch can save you from. Sign-out awaited exactly
  /// that and stopped dead before it reached anything else.
  bool get _supported => !kIsWeb;

  /// Asks, registers, and returns whether notifications will actually arrive.
  Future<bool> register() async {
    if (!_supported) return false;

    final settings = await _messaging.requestPermission();

    if (settings.authorizationStatus == AuthorizationStatus.denied) {
      return false;
    }

    final token = await _tokenOrNull();

    if (token == null) return false;

    await _send(token);

    // FCM rotates a registration without warning. A rotation that is not
    // forwarded looks exactly like a phone that stopped caring.
    _messaging.onTokenRefresh.listen((refreshed) async {
      try {
        await _send(refreshed);
      } catch (error) {
        if (kDebugMode) {
          debugPrint('Could not forward a refreshed FCM token: $error');
        }
      }
    });

    return true;
  }

  /// Called on sign-out. The next person to hold this phone must not receive
  /// notifications about a family they have nothing to do with.
  Future<void> unregister() async {
    if (!_supported) return;

    final token = await _tokenOrNull();

    if (token == null) return;

    try {
      await _api.delete(ApiPaths.devices, body: {'token': token});
    } on ApiException catch (error) {
      // Offline, most likely. The server also drops the registration when FCM
      // reports it dead, so this is not the only defence.
      if (kDebugMode) {
        debugPrint('Could not unregister this device: ${error.message}');
      }
    }

    await _messaging.deleteToken();
  }

  Future<void> _send(String token) => _api.post<void>(
    ApiPaths.devices,
    body: {'token': token, 'platform': Platform.isIOS ? 'ios' : 'android'},
    parse: (_) {},
  );

  Future<String?> _tokenOrNull() async {
    try {
      // On iOS the FCM token only exists once APNs has issued one. On a
      // simulator, or before the APNs key is uploaded, there is simply no
      // token — which is a configuration state, not a failure to report.
      // Bounded, because the failure mode that matters is not an exception.
      // Anything waiting on this is in the middle of something a person is
      // watching — signing out, or opening the app.
      return await _messaging.getToken().timeout(const Duration(seconds: 8));
    } catch (error) {
      if (kDebugMode) debugPrint('No FCM token available: $error');

      return null;
    }
  }
}
