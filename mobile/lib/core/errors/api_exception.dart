import 'package:dio/dio.dart';

/// A failed request, in terms the UI can act on.
///
/// Dio's own errors describe transport; this describes what went wrong for the
/// person using the app, which is a different question. Every failure reaches
/// the UI as one of these, so no screen has to branch on DioExceptionType.
class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.statusCode,
    this.code,
    this.errors = const {},
  });

  final String message;
  final int? statusCode;

  /// The machine-readable code from the envelope, e.g. `UNION_AMBIGUOUS`.
  final String? code;

  /// Field-level validation messages, keyed by field name.
  final Map<String, List<String>> errors;

  bool get isUnauthenticated => statusCode == 401;
  bool get isForbidden => statusCode == 403;
  bool get isNotFound => statusCode == 404;
  bool get isValidation => statusCode == 422;
  bool get isThrottled => statusCode == 429;
  bool get isOffline => statusCode == null;

  /// The first message for a field, for inline form errors.
  String? errorFor(String field) => errors[field]?.firstOrNull;

  factory ApiException.fromDio(DioException error) {
    final response = error.response;
    final data = response?.data;

    if (data is Map) {
      final json = data.cast<String, dynamic>();

      return ApiException(
        message: json['message'] as String? ?? _fallbackFor(error),
        statusCode: response?.statusCode,
        code: json['code'] as String?,
        errors: _parseErrors(json['errors']),
      );
    }

    return ApiException(
      message: _fallbackFor(error),
      statusCode: response?.statusCode,
    );
  }

  static Map<String, List<String>> _parseErrors(dynamic raw) {
    if (raw is! Map) return const {};

    return raw.map(
      (key, value) => MapEntry(
        key.toString(),
        value is List
            ? value.map((e) => e.toString()).toList(growable: false)
            : <String>[value.toString()],
      ),
    );
  }

  /// Said the way somebody would say it, not the way a stack trace would.
  static String _fallbackFor(DioException error) => switch (error.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout =>
          'The server took too long to answer. Check your connection and try again.',
        DioExceptionType.connectionError =>
          'Cannot reach My Generation. Check your connection.',
        DioExceptionType.badCertificate =>
          'The connection is not secure and was refused.',
        DioExceptionType.cancel => 'The request was cancelled.',
        _ => 'Something went wrong. Please try again.',
      };

  @override
  String toString() => 'ApiException($statusCode, $code): $message';
}

extension _FirstOrNull<E> on List<E> {
  E? get firstOrNull => isEmpty ? null : first;
}
