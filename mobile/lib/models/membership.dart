/// Belonging to a tribe, clan or family branch.
///
/// Pending grants nothing at all — the applicant sees exactly what a stranger
/// sees until somebody approves it, and the UI says so rather than implying
/// the request was enough.
class Membership {
  const Membership({
    required this.ulid,
    required this.status,
    this.scopeType,
    this.scopeUlid,
    this.scopeName,
  });

  final String ulid;
  final String status;
  final String? scopeType;
  final String? scopeUlid;
  final String? scopeName;

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
    );
  }
}
