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
    this.userName,
    this.requestedAt,
    this.joinedAt,
    this.applicantName,
    this.email,
    this.fatherName,
    this.motherName,
    this.grandfatherName,
    this.grandmotherName,
    this.country,
    this.contact,
    this.photoUrl,
  });

  final String ulid;
  final String status;
  final String? scopeType;
  final String? scopeUlid;
  final String? scopeName;

  /// The account they signed up with.
  final String? userName;

  final DateTime? requestedAt;
  final DateTime? joinedAt;

  /// What they told us about themselves. Sent only to the applicant and to
  /// whoever administers the scope they asked to join.
  final String? applicantName;
  final String? email;
  final String? fatherName;
  final String? motherName;
  final String? grandfatherName;
  final String? grandmotherName;
  final String? country;
  final String? contact;

  /// Signed and short-lived. Identification for a reviewer, never a family
  /// photograph.
  final String? photoUrl;

  bool get isActive => status == 'active';
  bool get isPending => status == 'pending';

  /// What they wrote, falling back to the account they signed up with — a
  /// tribe membership carries no answers at all.
  String get name => applicantName ?? userName ?? 'Someone';

  /// Label to answer, in the order a reviewer reads them. Empty entries are
  /// left out rather than shown blank.
  Map<String, String> get answers => {
    for (final entry in <String, String?>{
      'Name': applicantName,
      'Father': fatherName,
      'Mother': motherName,
      'Grandfather': grandfatherName,
      'Grandmother': grandmotherName,
      'Country': country,
      'Contact': contact,
    }.entries)
      if (entry.value != null && entry.value!.isNotEmpty)
        entry.key: entry.value!,
  };

  factory Membership.fromJson(Map<String, dynamic> json) {
    final scope = (json['scope'] as Map?)?.cast<String, dynamic>();

    // Read defensively: no answers at all arrives as a JSON array rather than
    // an object, and casting that to a Map throws — which is how one empty row
    // once failed the parse of an entire list.
    final raw = json['applicant'];
    final applicant = raw is Map ? raw.cast<String, dynamic>() : const {};

    String? said(String key) => applicant[key] as String?;

    return Membership(
      ulid: json['ulid'] as String,
      status: json['status'] as String? ?? 'pending',
      scopeType: scope?['type'] as String?,
      scopeUlid: scope?['ulid'] as String?,
      scopeName: scope?['name'] as String?,
      userName: (json['user'] as Map?)?['name'] as String?,
      requestedAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
      joinedAt: DateTime.tryParse(json['approved_at'] as String? ?? ''),
      applicantName: said('name'),
      email: said('email'),
      fatherName: said('father'),
      motherName: said('mother'),
      grandfatherName: said('grandfather'),
      grandmotherName: said('grandmother'),
      country: said('country'),
      contact: said('contact'),
      photoUrl: said('photo_url'),
    );
  }
}
