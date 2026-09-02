/// A person as the API returns them, already masked.
///
/// The server decides what this contains. `redacted` means fields were
/// withheld, `placeholder` means the record exists but this viewer may not see
/// who it is — the node still occupies its position, because hiding it would
/// misrepresent everybody else's lineage.
class PersonSummary {
  const PersonSummary({
    required this.ulid,
    required this.displayName,
    required this.gender,
    required this.isLiving,
    required this.redacted,
    this.placeholder = false,
    this.nativeName,
    this.birthDisplay,
    this.birthYear,
    this.deathDisplay,
    this.deathYear,
    this.photoUrl,
    this.verificationStatus,
    this.hasOpenDispute = false,
    this.generationLabel,
    this.depth,
  });

  final String ulid;
  final String displayName;
  final String gender;
  final bool isLiving;

  /// True when the server withheld something. The UI shows a quiet indicator —
  /// a person is entitled to know the record is fuller than what they see.
  final bool redacted;
  final bool placeholder;

  final String? nativeName;
  final String? birthDisplay;
  final int? birthYear;
  final String? deathDisplay;
  final int? deathYear;
  final String? photoUrl;
  final String? verificationStatus;
  final bool hasOpenDispute;
  final String? generationLabel;

  /// Layer relative to the focus of a tree: negative up, positive down.
  final int? depth;

  bool get isVerified => verificationStatus == 'verified';

  /// "1920–1998", "b. 1975", or nothing when no date is known or permitted.
  String? get lifespan {
    if (birthDisplay == null && deathDisplay == null) return null;
    if (birthDisplay != null && deathDisplay != null) return '$birthDisplay–$deathDisplay';
    return birthDisplay != null ? 'b. $birthDisplay' : 'd. $deathDisplay';
  }

  factory PersonSummary.fromJson(Map<String, dynamic> json) {
    final birth = (json['birth'] as Map?)?.cast<String, dynamic>();
    final death = (json['death'] as Map?)?.cast<String, dynamic>();

    return PersonSummary(
      ulid: json['ulid'] as String,
      displayName: json['display_name'] as String? ?? 'Unknown',
      gender: json['gender'] as String? ?? 'unknown',
      isLiving: json['is_living'] as bool? ?? true,
      redacted: json['redacted'] as bool? ?? false,
      placeholder: json['placeholder'] as bool? ?? false,
      nativeName: json['native_name'] as String?,
      birthDisplay: birth?['display'] as String?,
      birthYear: birth?['year'] as int?,
      deathDisplay: death?['display'] as String?,
      deathYear: death?['year'] as int?,
      photoUrl: json['photo_url'] as String?,
      verificationStatus: json['verification_status'] as String?,
      hasOpenDispute: json['has_open_dispute'] as bool? ?? false,
      generationLabel: json['generation_label'] as String?,
      depth: json['depth'] as int?,
    );
  }
}
