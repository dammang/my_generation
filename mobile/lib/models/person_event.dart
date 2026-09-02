/// One entry in a person's chronicle.
///
/// The date is carried as the source wrote it. "abt. 1902" is evidence about
/// how well the date is known, and normalising it to 1902 for display quietly
/// promotes a guess to a fact.
class PersonEvent {
  const PersonEvent({
    required this.ulid,
    required this.typeSlug,
    required this.typeLabel,
    required this.category,
    this.title,
    this.description,
    this.year,
    this.dateDisplay,
    this.precision = 'exact',
    this.verificationStatus,
    this.place,
    this.fromPlace,
    this.toPlace,
  });

  final String ulid;
  final String typeSlug;
  final String typeLabel;
  final String category;
  final String? title;
  final String? description;
  final int? year;
  final String? dateDisplay;
  final String? precision;
  final String? verificationStatus;
  final String? place;

  /// Migration carries both ends of the move, which is what turns a list of
  /// events into a family's journey.
  final String? fromPlace;
  final String? toPlace;

  /// Precisions that mean the date is a guess or a bound, not merely coarse.
  ///
  /// "1926" is a *known* year recorded to year precision — showing it as
  /// doubtful would misrepresent the record. "abt. 1926" is the doubtful one.
  static const _uncertainPrecisions = {
    'about',
    'decade',
    'before',
    'after',
    'between',
    'unknown',
  };

  bool get isUncertain => _uncertainPrecisions.contains(precision);

  /// The wording, but only when it says more than the year already shown in
  /// the timeline's gutter — otherwise "1926" would appear twice on one entry.
  String? get dateDetail {
    final display = dateDisplay;
    if (display == null || display.isEmpty) return null;
    return display == year?.toString() ? null : display;
  }
  bool get isVerified => verificationStatus == 'verified';
  bool get isMigration => fromPlace != null || toPlace != null;

  /// What the entry is called: the contributor's own title if they wrote one,
  /// otherwise the event type.
  String get heading =>
      (title?.trim().isNotEmpty ?? false) ? title! : typeLabel;

  /// "Chin Hills → Kalaymyo" for a move, the single place otherwise.
  String? get placeLine {
    if (isMigration) {
      final from = fromPlace ?? 'somewhere';
      final to = toPlace ?? 'somewhere';
      return '$from → $to';
    }
    return place;
  }

  factory PersonEvent.fromJson(Map<String, dynamic> json) {
    final type = (json['type'] as Map?)?.cast<String, dynamic>();
    String? placeName(Object? raw) =>
        raw is Map ? raw['name'] as String? : null;

    return PersonEvent(
      ulid: json['ulid'] as String,
      typeSlug: type?['slug'] as String? ?? 'other',
      typeLabel: type?['label'] as String? ?? 'Event',
      category: type?['category'] as String? ?? 'other',
      title: json['title'] as String?,
      description: json['description'] as String?,
      year: json['year'] as int?,
      dateDisplay: json['date_display'] as String?,
      precision: json['date_precision'] as String?,
      verificationStatus: json['verification_status'] as String?,
      place: placeName(json['place']),
      fromPlace: placeName(json['from_place']),
      toPlace: placeName(json['to_place']),
    );
  }
}

/// A timeline, plus whether it is empty or merely withheld.
///
/// Those are different facts and must not render the same. "No events recorded"
/// invites a contribution; "hidden" tells the truth about why the screen is
/// blank, and inviting a contribution there would be a lie.
class Timeline {
  const Timeline({
    required this.events,
    required this.withheld,
    this.unavailableOffline = false,
  });

  const Timeline.withheldFrom() : events = const [], withheld = true, unavailableOffline = false;

  /// Not withheld and not empty — simply not saved on this device. Three
  /// different facts, and telling somebody a life is "private" when the phone
  /// merely has no copy would be a lie about their own family.
  const Timeline.notOnDevice()
      : events = const [],
        withheld = false,
        unavailableOffline = true;

  final List<PersonEvent> events;
  final bool withheld;
  final bool unavailableOffline;

  bool get isEmpty => events.isEmpty && !withheld && !unavailableOffline;
}

/// An entry in the event vocabulary, for the "add an event" picker.
class EventTypeOption {
  const EventTypeOption({
    required this.slug,
    required this.label,
    required this.category,
    this.icon,
  });

  final String slug;
  final String label;
  final String category;
  final String? icon;

  factory EventTypeOption.fromJson(Map<String, dynamic> json) =>
      EventTypeOption(
        slug: json['slug'] as String,
        label: json['label'] as String? ?? json['slug'] as String,
        category: json['category'] as String? ?? 'other',
        icon: json['icon'] as String?,
      );
}
