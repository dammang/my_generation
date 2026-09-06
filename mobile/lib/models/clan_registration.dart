/// A request to start a clan.
///
/// Held apart from a clan because until somebody approves it, no clan exists —
/// only the record that it was asked for.
class ClanRegistration {
  const ClanRegistration({
    required this.ulid,
    required this.status,
    required this.name,
    required this.canDecide,
    required this.canWithdraw,
    this.nativeName,
    this.statement,
    this.decisionNote,
    this.tribeName,
    this.requesterName,
    this.clanName,
    this.clanUlid,
    this.createdAt,
  });

  final String ulid;
  final String status;
  final String name;

  /// What the reader may do with it, decided by the server.
  ///
  /// The client never works this out for itself: it would need to model
  /// scoped permissions to do it, and a screen that offers a button the
  /// server then refuses is worse than one that never offered it.
  final bool canDecide;
  final bool canWithdraw;

  final String? nativeName;
  final String? statement;
  final String? decisionNote;
  final String? tribeName;
  final String? requesterName;

  /// What the request became, once it became one.
  final String? clanName;
  final String? clanUlid;

  final DateTime? createdAt;

  bool get isPending => status == 'pending';
  bool get isApproved => status == 'approved';

  factory ClanRegistration.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic>? nested(String key) =>
        (json[key] as Map?)?.cast<String, dynamic>();

    return ClanRegistration(
      ulid: json['ulid'] as String,
      status: json['status'] as String? ?? 'pending',
      name: json['name'] as String? ?? '',
      canDecide: json['can_decide'] as bool? ?? false,
      canWithdraw: json['can_withdraw'] as bool? ?? false,
      nativeName: json['native_name'] as String?,
      statement: json['statement'] as String?,
      decisionNote: json['decision_note'] as String?,
      tribeName: nested('tribe')?['name'] as String?,
      requesterName: nested('requester')?['name'] as String?,
      clanName: nested('clan')?['name'] as String?,
      clanUlid: nested('clan')?['ulid'] as String?,
      createdAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
    );
  }
}
