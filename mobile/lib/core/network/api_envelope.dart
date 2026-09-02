/// The API's response envelope.
///
/// Every endpoint answers in the same shape, so the app has one parser and one
/// error path rather than a per-endpoint guess:
///
///   { "success": true,  "data": …, "meta": {…}, "warnings": [] }
///   { "success": false, "message": "…", "errors": {…}, "code": "…" }
class ApiEnvelope<T> {
  const ApiEnvelope({
    required this.success,
    this.data,
    this.meta = const {},
    this.warnings = const [],
  });

  final bool success;
  final T? data;
  final Map<String, dynamic> meta;
  final List<ApiWarning> warnings;

  bool get hasWarnings => warnings.isNotEmpty;

  static ApiEnvelope<T> fromJson<T>(
    Map<String, dynamic> json,
    T Function(dynamic data) parse,
  ) {
    return ApiEnvelope<T>(
      success: json['success'] as bool? ?? false,
      data: json['data'] == null ? null : parse(json['data']),
      meta: (json['meta'] as Map?)?.cast<String, dynamic>() ?? const {},
      warnings: ((json['warnings'] as List?) ?? const [])
          .whereType<Map>()
          .map((w) => ApiWarning.fromJson(w.cast<String, dynamic>()))
          .toList(growable: false),
    );
  }
}

/// A doubt the server recorded alongside a successful write.
///
/// Genealogy writes routinely succeed *and* carry doubt — "born 20 years after
/// the father's recorded death" is worth flagging, not refusing. The UI shows
/// these; it never treats one as a failure.
class ApiWarning {
  const ApiWarning({required this.code, required this.message, this.field});

  final String code;
  final String message;
  final String? field;

  factory ApiWarning.fromJson(Map<String, dynamic> json) => ApiWarning(
        code: json['code'] as String? ?? 'WARNING',
        message: json['message'] as String? ?? '',
        field: json['field'] as String?,
      );
}
