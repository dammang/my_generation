import 'joinable_scope.dart';

/// A clan as the joining list shows it.
class ClanSummary {
  const ClanSummary({
    required this.ulid,
    required this.name,
    this.nativeName,
    this.description,
    this.tribeName,
    this.levelLabel,
    this.peopleCount = 0,
  });

  final String ulid;
  final String name;
  final String? nativeName;
  final String? description;
  final String? tribeName;

  /// One tribe's "Sub-clan" is another's "Phung", so the label travels with
  /// the clan rather than being assumed here.
  final String? levelLabel;

  final int peopleCount;

  /// "ZOMI · Clan · 327 people"
  String get subtitle => [
    if (tribeName != null && tribeName!.isNotEmpty) tribeName!,
    if (levelLabel != null && levelLabel!.isNotEmpty) levelLabel!,
    peopleCount == 1 ? '1 person' : '$peopleCount people',
  ].join(' · ');

  JoinableScope get joinable => JoinableScope(
    type: 'clan',
    ulid: ulid,
    name: name,
    subtitle: subtitle,
    nativeName: nativeName,
    description: description,
  );

  factory ClanSummary.fromJson(Map<String, dynamic> json) => ClanSummary(
    ulid: json['ulid'] as String,
    name: json['name'] as String? ?? '',
    nativeName: json['native_name'] as String?,
    description: json['description'] as String?,
    tribeName: (json['tribe'] as Map?)?['name'] as String?,
    levelLabel: json['level_label'] as String?,
    peopleCount: (json['counts'] as Map?)?['people'] as int? ?? 0,
  );
}
