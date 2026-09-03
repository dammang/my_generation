import 'dart:convert';

import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
import '../database/app_database.dart';
import '../models/api_user.dart';
import '../services/secure_storage_service.dart';

/// Everything the app does with an account.
///
/// The repository is where the API and the local database are reconciled — the
/// one place that knows both exist. Nothing above it constructs a request or
/// touches a table.
class AuthRepository {
  AuthRepository({
    required ApiClient api,
    required SecureStorageService storage,
    required AppDatabase database,
  })  : _api = api,
        _storage = storage,
        _database = database;

  final ApiClient _api;
  final SecureStorageService _storage;
  final AppDatabase _database;

  Future<ApiUser> login({
    required String email,
    required String password,
    String deviceName = 'mobile',
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.login,
      body: {'email': email, 'password': password, 'device_name': deviceName},
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return _persist(envelope.data!);
  }

  /// Trades a verified Firebase identity for a session here.
  ///
  /// The ID token is used once and not stored. What comes back is the same
  /// Sanctum token every other call already uses, which is what keeps the
  /// permission model, the scopes and the offline queue working unchanged.
  Future<ApiUser> exchangeFirebaseToken({
    required String idToken,
    String? locale,
    String deviceName = 'mobile',
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.firebaseExchange,
      body: {
        'id_token': idToken,
        'locale': ?locale,
        'device_name': deviceName,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return _persist(envelope.data!);
  }

  Future<ApiUser> register({
    required String name,
    required String email,
    required String password,
    String deviceName = 'mobile',
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.register,
      body: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
        'device_name': deviceName,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return _persist(envelope.data!);
  }

  /// The signed-in account, straight from the server.
  ///
  /// Also the app's liveness check: it proves the token is still accepted and
  /// returns the viewer's current scopes and permissions, which may have
  /// changed since sign-in.
  Future<ApiUser> me() async {
    final envelope = await _api.get<Map<String, dynamic>>(
      ApiPaths.me,
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    final user = ApiUser.fromJson(envelope.data!);

    await _storage.writeAccount(jsonEncode(user.toJson()));

    return user;
  }

  /// The last account the server confirmed, for opening the app without a
  /// connection.
  ///
  /// Its permissions are as stale as the device is old, which is fine: they
  /// decide what to *offer*, never what is allowed. Every write is still
  /// authorised by the server when it eventually arrives.
  Future<ApiUser?> cachedAccount() async {
    final raw = await _storage.readAccount();

    if (raw == null) return null;

    try {
      return ApiUser.fromJson((jsonDecode(raw) as Map).cast<String, dynamic>());
    } catch (_) {
      // A stored account from an older build. Not worth failing startup over.
      return null;
    }
  }

  Future<void> logout() async {
    try {
      await _api.post<void>(ApiPaths.logout, parse: (_) {});
    } finally {
      // Local state is cleared whatever the server said. A failed sign-out that
      // leaves a token and a populated cache on the device is worse than a
      // token the server still considers valid.
      await _clearLocal();
    }
  }

  Future<bool> hasToken() async => (await _storage.readToken())?.isNotEmpty ?? false;

  Future<ApiUser> _persist(Map<String, dynamic> payload) async {
    final token = payload['token'] as String;
    final user = ApiUser.fromJson((payload['user'] as Map).cast<String, dynamic>());

    // Anything cached belongs to whoever was signed in before.
    await _database.wipe();

    await _storage.writeToken(token);
    await _storage.writeUserUlid(user.ulid);
    _api.forgetToken();

    return user;
  }

  Future<void> _clearLocal() async {
    await _storage.clear();
    _api.forgetToken();
    await _database.wipe();
  }
}
