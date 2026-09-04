/// A family narrative.
///
/// A listing carries [summary] and no [body] — the server withholds the full
/// text until a single story is asked for, so a list of twenty is twenty
/// summaries rather than twenty essays over somebody's mobile data.
class Story {
  const Story({
    required this.ulid,
    required this.title,
    required this.storyType,
    required this.visibility,
    this.summary,
    this.body,
    this.authorName,
    this.subjectName,
    this.eraStartYear,
    this.eraEndYear,
    this.verificationStatus,
  });

  final String ulid;
  final String title;
  final String storyType;
  final String visibility;
  final String? summary;

  /// Null in a listing. Present once the story itself has been fetched.
  final String? body;

  final String? authorName;
  final String? subjectName;
  final int? eraStartYear;
  final int? eraEndYear;
  final String? verificationStatus;

  bool get hasBody => (body?.trim().isNotEmpty ?? false);

  bool get isVerified => verificationStatus == 'verified';

  /// Says who may read it, in the words the person filing it would use —
  /// "Family" rather than "family", and nothing at all for a story that is
  /// simply public, where a badge would be noise.
  String? get audienceLabel => switch (visibility) {
    'private' => 'Only you',
    'family' => 'Family',
    'clan' => 'Clan',
    'tribe' => 'Tribe',
    _ => null,
  };

  /// "1926–1998", "from 1926", or nothing. A story about a period is easier
  /// to place with the period on it.
  String? get eraLabel {
    if (eraStartYear == null && eraEndYear == null) return null;
    if (eraStartYear != null && eraEndYear != null) {
      return '$eraStartYear–$eraEndYear';
    }
    return eraStartYear != null ? 'from $eraStartYear' : 'until $eraEndYear';
  }

  factory Story.fromJson(Map<String, dynamic> json) {
    String? nameOf(Object? raw) =>
        raw is Map ? (raw['name'] ?? raw['display_name']) as String? : null;

    return Story(
      ulid: json['ulid'] as String,
      title: json['title'] as String? ?? 'Untitled',
      storyType: json['story_type'] as String? ?? 'narrative',
      visibility: json['visibility'] as String? ?? 'family',
      summary: json['summary'] as String?,
      body: json['body'] as String?,
      authorName: nameOf(json['author']),
      subjectName: nameOf(json['subject']),
      eraStartYear: json['era_start_year'] as int?,
      eraEndYear: json['era_end_year'] as int?,
      verificationStatus: json['verification_status'] as String?,
    );
  }
}
