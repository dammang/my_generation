import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// The Sanctum token, kept in the platform keystore.
///
/// Keychain on iOS, EncryptedSharedPreferences on Android — never
/// SharedPreferences and never the local database, both of which are readable
/// from a backup or a rooted device.
class SecureStorageService {
  SecureStorageService([FlutterSecureStorage? storage])
      : _storage = storage ??
            const FlutterSecureStorage(
              // Android defaults to AES-GCM with RSA key wrapping in the
              // hardware KeyStore, which is stronger than the
              // EncryptedSharedPreferences option earlier versions exposed.
              aOptions: AndroidOptions(),
              // first_unlock rather than always: the token stays unreadable
              // until the device has been unlocked at least once since boot.
              iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
            );

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'auth_token';
  static const _userUlidKey = 'auth_user_ulid';

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> writeToken(String token) => _storage.write(key: _tokenKey, value: token);

  Future<String?> readUserUlid() => _storage.read(key: _userUlidKey);

  Future<void> writeUserUlid(String ulid) => _storage.write(key: _userUlidKey, value: ulid);

  /// Called on sign-out and whenever the server rejects the token.
  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userUlidKey);
  }
}
