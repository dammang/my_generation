import 'revision.dart';

/// One field a proposal would change.
class DiffEntry {
  const DiffEntry({
    required this.field,
    required this.label,
    this.before,
    this.after,
  });

  final String field;
  final String label;
  final String? before;
  final String? after;

  /// An empty value is a fact — "nothing recorded" — and rendering it as a
  /// blank makes the row look broken instead of informative.
  String get beforeText => RevisionEntry.readable(before);
  String get afterText => RevisionEntry.readable(after);

  factory DiffEntry.fromJson(Map<String, dynamic> json) => DiffEntry(
        field: json['field'] as String? ?? '',
        label: json['label'] as String? ?? '',
        before: json['before']?.toString(),
        after: json['after']?.toString(),
      );
}

/// A proposed change, as it appears in a queue.
class ChangeRequestSummary {
  const ChangeRequestSummary({
    required this.ulid,
    required this.status,
    required this.operation,
    required this.diff,
    this.reason,
    this.targetUlid,
    this.targetLabel,
    this.requestedByName,
    this.decidedByName,
    this.submittedAt,
    this.decidedAt,
    this.reviewComments = const [],
  });

  final String ulid;
  final String status;
  final String operation;
  final List<DiffEntry> diff;
  final String? reason;
  final String? targetUlid;
  final String? targetLabel;
  final String? requestedByName;
  final String? decidedByName;
  final DateTime? submittedAt;
  final DateTime? decidedAt;
  final List<String> reviewComments;

  bool get isPending => status == 'pending';
  bool get isSuperseded => status == 'superseded';

  String get statusLabel => switch (status) {
        'pending' => 'Waiting for review',
        'approved' => 'Approved',
        'rejected' => 'Not accepted',
        'withdrawn' => 'Withdrawn',
        'superseded' => 'The record changed first',
        'needs_info' => 'More detail needed',
        _ => status,
      };

  factory ChangeRequestSummary.fromJson(Map<String, dynamic> json) {
    final target = (json['target'] as Map?)?.cast<String, dynamic>();

    DateTime? at(String key) {
      final raw = json[key] as String?;
      return raw == null ? null : DateTime.tryParse(raw);
    }

    return ChangeRequestSummary(
      ulid: json['ulid'] as String,
      status: json['status'] as String? ?? 'pending',
      operation: json['operation'] as String? ?? 'update',
      diff: ((json['diff'] as List?) ?? const [])
          .whereType<Map>()
          .map((e) => DiffEntry.fromJson(e.cast<String, dynamic>()))
          .toList(growable: false),
      reason: json['reason'] as String?,
      targetUlid: target?['ulid'] as String?,
      targetLabel: target?['label'] as String?,
      requestedByName: (json['requested_by'] as Map?)?['name'] as String?,
      decidedByName: (json['decided_by'] as Map?)?['name'] as String?,
      submittedAt: at('submitted_at'),
      decidedAt: at('decided_at'),
      reviewComments: ((json['reviews'] as List?) ?? const [])
          .whereType<Map>()
          .map((r) => r['comment'] as String?)
          .whereType<String>()
          .where((c) => c.trim().isNotEmpty)
          .toList(growable: false),
    );
  }
}

/// What an edit actually did.
///
/// The same request either lands or becomes a proposal depending on whether the
/// record is verified and what the editor is entitled to do. The UI must be
/// able to tell the difference — telling somebody "saved" when it is waiting
/// for review is the one thing this screen must never do.
class EditOutcome {
  const EditOutcome({required this.applied, this.proposal});

  const EditOutcome.applied() : applied = true, proposal = null;

  final bool applied;
  final ChangeRequestSummary? proposal;
}
