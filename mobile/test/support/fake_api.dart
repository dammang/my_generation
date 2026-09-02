import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:my_generation/core/network/api_client.dart';
import 'package:my_generation/core/network/auth_interceptor.dart';
import 'package:my_generation/services/secure_storage_service.dart';

/// A canned reply for one request.
class FakeReply {
  const FakeReply(this.status, this.body);

  final int status;
  final Map<String, dynamic> body;
}

/// Serves canned replies at the HTTP boundary.
///
/// Faking at the adapter rather than at the repository means the test still
/// runs through Dio, the envelope parser and the model factories — which is
/// where a shape mismatch with the real API would actually show up.
class FakeAdapter implements HttpClientAdapter {
  FakeAdapter(this.replies);

  /// Keyed by "METHOD /path". A queue per key, so a second attempt can get a
  /// different answer from the first — which is exactly the shape of a
  /// refusal followed by a corrected retry.
  final Map<String, List<FakeReply>> replies;

  final List<RequestOptions> received = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    received.add(options);

    final key = '${options.method} ${options.path}';
    final queue = replies[key];

    if (queue == null || queue.isEmpty) {
      throw StateError('No fake reply for $key');
    }

    final reply = queue.length == 1 ? queue.first : queue.removeAt(0);

    return ResponseBody.fromString(
      jsonEncode(reply.body),
      reply.status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

/// A storage that never touches the platform keystore.
class FakeSecureStorage extends SecureStorageService {
  @override
  Future<String?> readToken() async => 'test-token';

  @override
  Future<String?> readUserUlid() async => null;

  @override
  Future<void> writeToken(String token) async {}

  @override
  Future<void> writeUserUlid(String ulid) async {}

  @override
  Future<void> clear() async {}
}

/// An ApiClient wired to [adapter].
ApiClient fakeApiClient(FakeAdapter adapter) {
  final dio = ApiClient.buildDio(baseUrl: 'http://test.local')
    ..httpClientAdapter = adapter;

  final interceptor = AuthInterceptor(
    FakeSecureStorage(),
    onUnauthenticated: () {},
  );

  dio.interceptors.add(interceptor);

  return ApiClient(dio: dio, authInterceptor: interceptor);
}
