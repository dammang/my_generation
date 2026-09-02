import 'package:dio/dio.dart';

import '../../config/env.dart';
import '../constants/api_paths.dart';
import '../errors/api_exception.dart';
import 'api_envelope.dart';
import 'auth_interceptor.dart';

/// The single way the app talks to the API.
///
/// Unwraps the envelope, converts every failure into an [ApiException], and
/// keeps the details of Dio out of repositories entirely — so a change of HTTP
/// client would touch this file and nothing else.
class ApiClient {
  const ApiClient({
    required Dio dio,
    required AuthInterceptor authInterceptor,
  })  : _dio = dio,
        _authInterceptor = authInterceptor;

  final Dio _dio;
  final AuthInterceptor _authInterceptor;

  Dio get raw => _dio;

  /// Forget the cached token — after signing in as somebody else, or out.
  void forgetToken() => _authInterceptor.forget();

  static Dio buildDio({String? baseUrl}) {
    final dio = Dio(
      BaseOptions(
        baseUrl: baseUrl ?? ApiConfig.defaultBaseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 30),
        headers: const {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        // The envelope carries the real outcome, so every status is a valid
        // response to be parsed rather than an exception to be caught.
        validateStatus: (status) => status != null && status < 500,
      ),
    );

    if (Env.logRequests) {
      dio.interceptors.add(LogInterceptor(
        requestBody: true,
        responseBody: true,
        // Never log the token, even in development.
        requestHeader: false,
      ));
    }

    return dio;
  }

  Future<ApiEnvelope<T>> get<T>(
    String path, {
    Map<String, dynamic>? query,
    required T Function(dynamic data) parse,
  }) =>
      _send(() => _dio.get(path, queryParameters: query), parse);

  Future<ApiEnvelope<T>> post<T>(
    String path, {
    Object? body,
    required T Function(dynamic data) parse,
  }) =>
      _send(() => _dio.post(path, data: body), parse);

  Future<ApiEnvelope<T>> patch<T>(
    String path, {
    Object? body,
    required T Function(dynamic data) parse,
  }) =>
      _send(() => _dio.patch(path, data: body), parse);

  Future<ApiEnvelope<void>> delete(String path) =>
      _send(() => _dio.delete(path), (_) {});

  Future<ApiEnvelope<T>> _send<T>(
    Future<Response<dynamic>> Function() request,
    T Function(dynamic data) parse,
  ) async {
    try {
      final response = await request();
      final data = response.data;

      if (data is! Map) {
        throw ApiException(
          message: 'The server sent an unexpected response.',
          statusCode: response.statusCode,
        );
      }

      final json = data.cast<String, dynamic>();

      if (json['success'] == false) {
        throw ApiException(
          message: json['message'] as String? ?? 'The request failed.',
          statusCode: response.statusCode,
          code: json['code'] as String?,
          errors: _errors(json['errors']),
          // Domain failures arrive here, not through DioException: every
          // status under 500 is a valid response. Dropping meta on this path
          // would strip the detail the UI needs to offer a way out.
          meta: (json['meta'] as Map?)?.cast<String, dynamic>() ?? const {},
        );
      }

      return ApiEnvelope.fromJson<T>(json, parse);
    } on DioException catch (error) {
      throw ApiException.fromDio(error);
    }
  }

  static Map<String, List<String>> _errors(dynamic raw) {
    if (raw is! Map) return const {};

    return raw.map(
      (key, value) => MapEntry(
        key.toString(),
        value is List ? value.map((e) => e.toString()).toList() : <String>[value.toString()],
      ),
    );
  }
}
