/// Belonging to a tribe, clan or family branch.
///
/// Pending grants nothing at all — the applicant sees exactly what a stranger
/// sees until somebody approves it, and the UI says so rather than implying
/// the request was enough.
const _labels = {
  'name': 'Name',
  'father': 'Father',
  'mother': 'Mother',
  'grandfather': 'Grandfather',
  'grandmother': 'Grandmother',
  'country': 'Country',
  'contact': 'Contact',
};

/// Read defensively. An empty answer set arrives as a JSON array rather than
/// an object, and casting it to a Map threw — which failed the parse of every
/// membership in the list because one of them had nothing to say.
String? _photoUrl(Object? raw) =>
    raw is Map ? raw['photo_url'] as String? : null;

Map<String, String> _answers(Object? raw) {
  if (raw is! Map) return const {};

  return {
    for (final entry in _labels.entries)
      if (raw[entry.key] case final String value when value.isNotEmpty)
        entry.value: value,
  };
}

class Membership {
  const Membership({
    required this.ulid,
    required this.status,
    this.scopeType,
    this.scopeUlid,
    this.scopeName,
    this.userName,
    this.requestedAt,
    this.answers = const {},
    this.photoUrl,
  });

  final String ulid;
  final String status;
  final String? scopeType;
  final String? scopeUlid;
  final String? scopeName;

  /// Who asked. Only ever sent to somebody who administers the scope.
  final String? userName;

  final DateTime? requestedAt;

  /// What the applicant said about themselves, label to answer, in the order
  /// a reviewer reads them. Sent only to the applicant and to whoever
  /// administers the scope.
  final Map<String, String> answers;

  /// Signed and short-lived. Identification for the reviewer, never a family
  /// photograph.
  final String? photoUrl;

  bool get isActive => status == 'active';
  bool get isPending => status == 'pending';

  factory Membership.fromJson(Map<String, dynamic> json) {
    final scope = (json['scope'] as Map?)?.cast<String, dynamic>();

    return Membership(
      ulid: json['ulid'] as String,
      status: json['status'] as String? ?? 'pending',
      scopeType: scope?['type'] as String?,
      scopeUlid: scope?['ulid'] as String?,
      scopeName: scope?['name'] as String?,
      userName: (json['user'] as Map?)?['name'] as String?,
      requestedAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
      answers: _answers(json['applicant']),
      photoUrl: _photoUrl(json['applicant']),
    );
  }
}
