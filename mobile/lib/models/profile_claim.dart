/// "This person is me", awaiting a decision.
class ProfileClaim {
  const ProfileClaim({
    required this.ulid,
    required this.status,
    this.personUlid,
    this.personName,
    this.decisionNote,
  });

  final String ulid;
  final String status;
  final String? personUlid;
  final String? personName;
  final String? decisionNote;

  bool get isPending => status == 'pending';
  bool get isApproved => status == 'approved';
  bool get isRejected => status == 'rejected';

  factory ProfileClaim.fromJson(Map<String, dynamic> json) {
    final person = (json['person'] as Map?)?.cast<String, dynamic>();

    return ProfileClaim(
      ulid: json['ulid'] as String,
      status: json['status'] as String? ?? 'pending',
      personUlid: person?['ulid'] as String?,
      personName: person?['display_name'] as String?,
      decisionNote: json['decision_note'] as String?,
    );
  }
}
