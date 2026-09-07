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
    this.generation,
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

  /// The same person on both scales, where the clan has set them.
  final GenerationStanding? generation;

  /// Layer relative to the focus of a tree: negative up, positive down.
  final int? depth;

  bool get isVerified => verificationStatus == 'verified';

  /// "1920–1998", "b. 1975", or nothing when no date is known or permitted.
  String? get lifespan {
    if (birthDisplay == null && deathDisplay == null) return null;
    if (birthDisplay != null && deathDisplay != null) {
      return '$birthDisplay–$deathDisplay';
    }
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
      generation: json['generation'] == null
          ? null
          : GenerationStanding.fromJson(
              (json['generation'] as Map).cast<String, dynamic>(),
            ),
      depth: json['depth'] as int?,
    );
  }
}

/// Where somebody stands, counted two ways.
///
/// A clan counts from an origin recent enough for the numbers to mean
/// something, while still descending from an ancestor much further back.
/// "11th generation from Pu Zo, 1st generation of Jasuan" is one person
/// described twice, and families say both.
class GenerationStanding {
  const GenerationStanding({
    this.number,
    this.origin,
    this.outerNumber,
    this.outerOrigin,
    this.beforeOrigin,
  });

  /// Counted from the clan's own origin. Null for anybody above it.
  final int? number;
  final String? origin;

  /// Counted from the older ancestor the clan descends from.
  final int? outerNumber;
  final String? outerOrigin;

  /// How far above the origin they stand, for the people counting starts after.
  final int? beforeOrigin;

  factory GenerationStanding.fromJson(Map<String, dynamic> json) =>
      GenerationStanding(
        number: json['number'] as int?,
        origin: json['origin'] as String?,
        outerNumber: json['outer_number'] as int?,
        outerOrigin: json['outer_origin'] as String?,
        beforeOrigin: json['before_origin'] as int?,
      );

  /// "11th generation from Pu Zo · 1st generation of Jasuan", dropping
  /// whichever half is not known rather than printing a blank.
  String? get summary {
    final parts = [
      if (outerNumber != null && outerOrigin != null)
        '${_ordinal(outerNumber!)} generation from $outerOrigin',
      if (number != null && origin != null)
        '${_ordinal(number!)} generation of $origin',
      if (beforeOrigin != null && origin != null)
        beforeOrigin == 1
            ? '1 generation before $origin'
            : '$beforeOrigin generations before $origin',
    ];

    return parts.isEmpty ? null : parts.join(' · ');
  }

  static String _ordinal(int n) => switch (n % 100) {
    11 || 12 || 13 => '${n}th',
    _ => switch (n % 10) {
      1 => '${n}st',
      2 => '${n}nd',
      3 => '${n}rd',
      _ => '${n}th',
    },
  };
}
