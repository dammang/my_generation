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

  /// Asks, registers, and returns whether notifications will actually arrive.
  Future<bool> register() async {
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
        if (kDebugMode) debugPrint('Could not forward a refreshed FCM token: $error');
      }
    });

    return true;
  }

  /// Called on sign-out. The next person to hold this phone must not receive
  /// notifications about a family they have nothing to do with.
  Future<void> unregister() async {
    final token = await _tokenOrNull();

    if (token == null) return;

    try {
      await _api.delete(ApiPaths.devices, body: {'token': token});
    } on ApiException catch (error) {
      // Offline, most likely. The server also drops the registration when FCM
      // reports it dead, so this is not the only defence.
      if (kDebugMode) debugPrint('Could not unregister this device: ${error.message}');
    }

    await _messaging.deleteToken();
  }

  Future<void> _send(String token) => _api.post<void>(
        ApiPaths.devices,
        body: {
          'token': token,
          'platform': Platform.isIOS ? 'ios' : 'android',
        },
        parse: (_) {},
      );

  Future<String?> _tokenOrNull() async {
    try {
      // On iOS the FCM token only exists once APNs has issued one. On a
      // simulator, or before the APNs key is uploaded, there is simply no
      // token — which is a configuration state, not a failure to report.
      return await _messaging.getToken();
    } catch (error) {
      if (kDebugMode) debugPrint('No FCM token available: $error');

      return null;
    }
  }
}
