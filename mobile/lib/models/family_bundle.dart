import 'person_summary.dart';

/// A marriage or partnership, with the children that belong to it.
///
/// Children hang off the union rather than off a person because that is where
/// they actually belong: a man with three marriages has three sets of children,
/// and a flat list of "his children" loses which mother each had.
class FamilyUnion {
  const FamilyUnion({
    required this.ulid,
    required this.partners,
    required this.children,
    required this.type,
    required this.status,
    this.startDisplay,
    this.endDisplay,
    this.place,
    this.childrenCount = 0,
  });

  final String ulid;
  final List<PersonSummary> partners;
  final List<PersonSummary> children;
  final String type;
  final String status;
  final String? startDisplay;
  final String? endDisplay;
  final String? place;

  /// The server's own count, which can exceed [children] when this viewer may
  /// not see every child. Showing children.length as the total would tell them
  /// a family is smaller than it is.
  final int childrenCount;

  /// The other partner, from one person's point of view.
  PersonSummary? partnerOther(String ulid) {
    for (final partner in partners) {
      if (partner.ulid != ulid) return partner;
    }
    return null;
  }

  /// "Married 1948", "Married 1948 – divorced 1961", or the status alone when
  /// no dates are recorded. A union with no dates is still a fact.
  String describe() {
    final parts = <String>[];
    if (startDisplay != null) parts.add('m. $startDisplay');
    if (endDisplay != null) parts.add('ended $endDisplay');
    if (parts.isEmpty) return _statusLabel;
    return parts.join(' · ');
  }

  String get _statusLabel => switch (status) {
    'married' => 'Married',
    'divorced' => 'Divorced',
    'widowed' => 'Widowed',
    'separated' => 'Separated',
    'partnered' => 'Partners',
    _ => 'Union',
  };

  factory FamilyUnion.fromJson(Map<String, dynamic> json) {
    List<PersonSummary> people(Object? raw) => ((raw as List?) ?? const [])
        .whereType<Map>()
        .map((e) => PersonSummary.fromJson(e.cast<String, dynamic>()))
        .toList(growable: false);

    final marriage = (json['marriage'] as Map?)?.cast<String, dynamic>();

    // A union ends by divorce or by separation; the server sends whichever it
    // holds as a plain date, and only the year is meaningful on a profile.
    final ended = (json['divorce_date'] ?? json['separation_date']) as String?;

    return FamilyUnion(
      ulid: json['ulid'] as String,
      partners: people(json['partners']),
      children: people(json['children']),
      type: json['union_type'] as String? ?? 'marriage',
      status: json['status'] as String? ?? 'unknown',
      startDisplay: marriage?['display'] as String?,
      endDisplay: ended == null || ended.length < 4
          ? null
          : ended.substring(0, 4),
      place: (json['marriage_place'] as Map?)?['name'] as String?,
      childrenCount: json['children_count'] as int? ?? 0,
    );
  }
}

/// Everybody one person is connected to, in one response.
///
/// Four round trips for parents, spouses, children and siblings would show the
/// family assembling itself on screen a piece at a time.
class FamilyBundle {
  const FamilyBundle({
    required this.person,
    required this.parents,
    required this.spouses,
    required this.children,
    required this.siblings,
    required this.unions,
    this.fromCache = false,
    this.link,
  });

  final PersonSummary person;
  final List<PersonSummary> parents;
  final List<PersonSummary> spouses;
  final List<PersonSummary> children;
  final List<PersonSummary> siblings;
  final List<FamilyUnion> unions;

  /// Assembled from the device. Marriages are not grouped offline, so the
  /// screen must not imply that a flat list of children is the whole truth.
  final bool fromCache;

  /// The family link already asked for, if any.
  final FamilyLink? link;

  bool get isEmpty =>
      parents.isEmpty &&
      spouses.isEmpty &&
      children.isEmpty &&
      siblings.isEmpty;

  /// Children with no union recorded — the parents are known but the marriage
  /// is not. Common in older records, and they must not silently disappear
  /// from a screen that groups children by union.
  List<PersonSummary> get unattachedChildren {
    final claimed = unions.expand((u) => u.children).map((c) => c.ulid).toSet();
    return children
        .where((child) => !claimed.contains(child.ulid))
        .toList(growable: false);
  }

  factory FamilyBundle.fromJson(Map<String, dynamic> json) {
    List<PersonSummary> people(String key) => ((json[key] as List?) ?? const [])
        .whereType<Map>()
        .map((e) => PersonSummary.fromJson(e.cast<String, dynamic>()))
        .toList(growable: false);

    return FamilyBundle(
      person: PersonSummary.fromJson(
        (json['person'] as Map).cast<String, dynamic>(),
      ),
      parents: people('parents'),
      spouses: people('spouses'),
      children: people('children'),
      siblings: people('siblings'),
      unions: ((json['unions'] as List?) ?? const [])
          .whereType<Map>()
          .map((e) => FamilyUnion.fromJson(e.cast<String, dynamic>()))
          .toList(growable: false),
      link: json['family_link'] is Map
          ? FamilyLink.fromJson(
              (json['family_link'] as Map).cast<String, dynamic>(),
            )
          : null,
    );
  }
}

/// "Link to another family", once it has been answered.
///
/// Either waiting for review or in effect. Asking a second time would file a
/// second proposal about the same woman, so the button stands down and says
/// where to change it instead.
class FamilyLink {
  const FamilyLink({
    required this.pending,
    required this.kind,
    required this.changeRequestUlid,
    this.label,
  });

  final bool pending;

  /// `branch` (a family line), `merge` (her own record) or `unlink`.
  final String kind;
  final String changeRequestUlid;

  /// The family's name, for a family-line link.
  final String? label;

  /// What the family tab says instead of offering the button.
  String get summary => switch ((kind, pending)) {
    ('unlink', _) =>
      'Unlinking from her family is waiting for review. Withdraw it from Edits.',
    ('merge', true) =>
      'Claimed as her own record — waiting for review. Change or withdraw it '
          'from Edits.',
    ('merge', false) => 'Linked to her own record.',
    (_, true) =>
      'Linking to the ${label ?? 'chosen'} family — waiting for review. '
          'Change or withdraw it from Edits.',
    (_, false) => 'Linked to the ${label ?? 'chosen'} family.',
  };

  factory FamilyLink.fromJson(Map<String, dynamic> json) => FamilyLink(
    pending: json['state'] == 'pending',
    kind: json['kind'] as String? ?? 'branch',
    changeRequestUlid: json['change_request_ulid'] as String? ?? '',
    label: json['label'] as String?,
  );
}
