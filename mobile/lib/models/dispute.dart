/// One version of a disputed fact.
class DisputeClaim {
  const DisputeClaim({
    required this.id,
    required this.value,
    required this.accepted,
    this.rationale,
    this.claimedByName,
    this.supporters = 0,
  });

  final int id;
  final String value;
  final bool accepted;
  final String? rationale;
  final String? claimedByName;
  final int supporters;

  factory DisputeClaim.fromJson(Map<String, dynamic> json) => DisputeClaim(
        id: json['id'] as int? ?? 0,
        value: json['value']?.toString() ?? '',
        accepted: json['accepted'] as bool? ?? false,
        rationale: json['rationale'] as String?,
        claimedByName: (json['claimed_by'] as Map?)?['name'] as String?,
        supporters: json['supporters'] as int? ?? 0,
      );
}

/// An open disagreement about one field.
///
/// Every version offered survives. Resolving records which one was accepted and
/// why, without deleting the others — in a family archive the fact that a
/// question was open is itself worth keeping.
class Dispute {
  const Dispute({
    required this.ulid,
    required this.field,
    required this.label,
    required this.status,
    required this.claims,
    this.resolution,
    this.resolutionNote,
    this.openedByName,
  });

  final String ulid;
  final String field;
  final String label;
  final String status;
  final List<DisputeClaim> claims;
  final String? resolution;
  final String? resolutionNote;
  final String? openedByName;

  bool get isOpen => status == 'open';

  String? get resolutionLabel => switch (resolution) {
        'claim_accepted' => 'One version was accepted',
        'both_recorded' => 'Both versions are recorded',
        'insufficient_evidence' => 'Not enough evidence either way',
        'withdrawn' => 'Withdrawn',
        _ => null,
      };

  factory Dispute.fromJson(Map<String, dynamic> json) => Dispute(
        ulid: json['ulid'] as String,
        field: json['field'] as String? ?? '',
        label: json['label'] as String? ?? '',
        status: json['status'] as String? ?? 'open',
        claims: ((json['claims'] as List?) ?? const [])
            .whereType<Map>()
            .map((e) => DisputeClaim.fromJson(e.cast<String, dynamic>()))
            .toList(growable: false),
        resolution: json['resolution'] as String?,
        resolutionNote: json['resolution_note'] as String?,
        openedByName: (json['opened_by'] as Map?)?['name'] as String?,
      );
}
