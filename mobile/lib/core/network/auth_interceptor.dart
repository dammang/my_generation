import 'package:dio/dio.dart';

import '../../services/secure_storage_service.dart';

/// Attaches the bearer token, and reacts when the server stops accepting it.
///
/// A 401 means the token is gone for good — revoked, expired, or the account
/// suspended. Clearing it locally and telling the app once is the only correct
/// response; retrying would just spend the rate limit.
class AuthInterceptor extends Interceptor {
  AuthInterceptor(this._storage, {required this.onUnauthenticated});

  final SecureStorageService _storage;
  final void Function() onUnauthenticated;

  String? _cachedToken;

  /// Kept in memory after the first read: the keystore is slow enough that
  /// paying for it on every request is noticeable while panning a tree.
  Future<String?> _token() async => _cachedToken ??= await _storage.readToken();

  void forget() => _cachedToken = null;

  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await _token();

    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }

    handler.next(options);
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      forget();
      await _storage.clear();
      onUnauthenticated();
    }

    handler.next(err);
  }
}
