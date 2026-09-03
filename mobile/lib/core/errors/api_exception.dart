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
    this.meta = const {},
    this.serverDetail,
  });

  final String message;
  final int? statusCode;

  /// What the server actually said, when that was not fit to show anyone.
  ///
  /// A 5xx body carries an exception message written for whoever maintains the
  /// server. Keeping it here means the UI shows [message] while logs and
  /// Crashlytics still get the sentence that explains the outage.
  final String? serverDetail;

  /// The machine-readable code from the envelope, e.g. `UNION_AMBIGUOUS`.
  final String? code;

  /// Field-level validation messages, keyed by field name.
  final Map<String, List<String>> errors;

  /// Structured detail for a failure the UI can help the user recover from —
  /// `UNION_AMBIGUOUS` carries the unions to choose between. Parsing ids out of
  /// a human sentence is not recovery, so the server sends them as data.
  final Map<String, dynamic> meta;

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
      final status = response?.statusCode;
      final serverMessage = json['message'] as String?;

      // A 4xx message is written for the person reading it — "That email is
      // already registered" — and is the most useful thing we can say. A 5xx
      // message is not: it is whatever the exception happened to contain, and
      // a misconfigured server will happily tell a grandmother it cannot
      // create an API client without credentials. Keep it for the logs and
      // say something true instead.
      final isServerFault = status != null && status >= 500;

      return ApiException(
        message: isServerFault ? _serverFaultMessage : serverMessage ?? _fallbackFor(error),
        statusCode: status,
        code: json['code'] as String?,
        errors: _parseErrors(json['errors']),
        meta: (json['meta'] as Map?)?.cast<String, dynamic>() ?? const {},
        serverDetail: isServerFault ? serverMessage : null,
      );
    }

    return ApiException(message: _fallbackFor(error), statusCode: response?.statusCode);
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

  /// Shown when the server itself failed. Deliberately says nothing about the
  /// cause: the person holding the phone did nothing wrong and can do nothing
  /// about it, and the detail is in [serverDetail] for the people who can.
  static const _serverFaultMessage =
      'Something went wrong on our end. Please try again in a moment.';

  /// Said the way somebody would say it, not the way a stack trace would.
  static String _fallbackFor(DioException error) => switch (error.type) {
    DioExceptionType.connectionTimeout ||
    DioExceptionType.sendTimeout ||
    DioExceptionType.receiveTimeout =>
      'The server took too long to answer. Check your connection and try again.',
    DioExceptionType.connectionError => 'Cannot reach My Generation. Check your connection.',
    DioExceptionType.badCertificate => 'The connection is not secure and was refused.',
    DioExceptionType.cancel => 'The request was cancelled.',
    _ => 'Something went wrong. Please try again.',
  };

  @override
  String toString() => serverDetail != null
      ? 'ApiException($statusCode, $code): $message [server: $serverDetail]'
      : 'ApiException($statusCode, $code): $message';
}

extension _FirstOrNull<E> on List<E> {
  E? get firstOrNull => isEmpty ? null : first;
}
