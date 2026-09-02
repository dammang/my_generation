/// One field changing, once.
class RevisionEntry {
  const RevisionEntry({
    required this.id,
    required this.label,
    required this.action,
    this.field,
    this.before,
    this.after,
    this.reason,
    this.at,
    this.changedByName,
    this.viaChangeRequest = false,
    this.sourceTitle,
  });

  final int id;
  final String label;
  final String action;
  final String? field;
  final String? before;
  final String? after;
  final String? reason;
  final DateTime? at;
  final String? changedByName;

  /// True when the change came through review rather than a direct edit. Worth
  /// showing: it says the value was agreed, not merely typed.
  final bool viaChangeRequest;
  final String? sourceTitle;

  bool get isFieldChange => field != null;

  String get beforeText => readable(before);
  String get afterText => readable(after);

  /// Values as a person would read them.
  ///
  /// The ledger stores what the column stored, so a boolean comes back as
  /// "true" and an enum as "needs_review". Showing a family "false → true"
  /// tells them nothing about what somebody actually changed.
  static String readable(String? value) {
    if (value == null || value.trim().isEmpty) return '—';

    return switch (value.trim()) {
      'true' => 'Yes',
      'false' => 'No',
      final other when other.contains('_') =>
        other.split('_').map(_capitalise).join(' '),
      final other => _capitalise(other),
    };
  }

  static String _capitalise(String word) =>
      word.isEmpty ? word : word[0].toUpperCase() + word.substring(1);

  factory RevisionEntry.fromJson(Map<String, dynamic> json) => RevisionEntry(
        id: json['id'] as int? ?? 0,
        label: json['label'] as String? ?? '',
        action: json['action'] as String? ?? 'updated',
        field: json['field'] as String?,
        before: json['before']?.toString(),
        after: json['after']?.toString(),
        reason: json['reason'] as String?,
        at: json['at'] == null ? null : DateTime.tryParse(json['at'] as String),
        changedByName: (json['changed_by'] as Map?)?['name'] as String?,
        viaChangeRequest: json['via_change_request'] as bool? ?? false,
        sourceTitle: (json['source'] as Map?)?['title'] as String?,
      );
}

/// A record's history, or the fact that it is not being shown.
class History {
  const History({required this.entries, required this.withheld});

  const History.withheldFrom() : entries = const [], withheld = true;

  final List<RevisionEntry> entries;
  final bool withheld;

  bool get isEmpty => entries.isEmpty && !withheld;
}
