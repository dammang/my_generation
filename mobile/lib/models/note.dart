/// Who a note is written for.
///
/// Its own scale rather than the privacy levels, because it answers a question
/// those cannot: "my descendants". A record's privacy is about who may see a
/// person; a note's audience is about who the writer is speaking to.
enum NoteAudience {
  descendants(
    'descendants',
    'My descendants',
    'Everybody descended from me, and nobody else',
  ),
  clan('clan', 'The clan', 'Anybody in this family\'s clan'),
  family('family', 'My close family', 'Cousins and nearer'),
  onlyMe('private', 'Only me', 'Written down, and read by nobody else');

  const NoteAudience(this.wire, this.label, this.describe);

  final String wire;
  final String label;
  final String describe;

  static NoteAudience? fromWire(String? value) {
    for (final audience in NoteAudience.values) {
      if (audience.wire == value) return audience;
    }

    return null;
  }
}

/// Something one member wanted said about a person, to a chosen audience.
class Note {
  const Note({
    required this.ulid,
    required this.body,
    required this.mine,
    this.audience,
    this.audienceLabel,
    this.authorName,
    this.writtenAt,
  });

  final String ulid;
  final String body;

  /// Whether this account wrote it, so the app can offer to remove it without
  /// working the answer out for itself.
  final bool mine;

  final NoteAudience? audience;

  /// What the server called the audience. Used when it names one this build
  /// does not know, so a note never displays a blank where its reach should be.
  final String? audienceLabel;

  final String? authorName;
  final DateTime? writtenAt;

  String get reach => audience?.label ?? audienceLabel ?? 'Limited';

  factory Note.fromJson(Map<String, dynamic> json) => Note(
    ulid: json['ulid'] as String,
    body: json['body'] as String? ?? '',
    mine: json['mine'] as bool? ?? false,
    audience: NoteAudience.fromWire(json['audience'] as String?),
    audienceLabel: json['audience_label'] as String?,
    authorName: (json['author'] as Map?)?['name'] as String?,
    writtenAt: DateTime.tryParse(json['written_at'] as String? ?? ''),
  );
}
