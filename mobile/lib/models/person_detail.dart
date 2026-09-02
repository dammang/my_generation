import 'person_summary.dart';

/// One person's full record, as far as this viewer is permitted to see it.
///
/// [PersonSummary] is what a tree node needs; this is what a profile needs. The
/// split is deliberate — a tree of 500 people should not carry 500 biographies
/// over the wire.
class PersonDetail {
  const PersonDetail({
    required this.summary,
    this.biography,
    this.birthPlace,
    this.deathPlace,
    this.tribeName,
    this.clanName,
    this.branchName,
    this.mergedIntoUlid,
  });

  final PersonSummary summary;
  final String? biography;
  final String? birthPlace;
  final String? deathPlace;
  final String? tribeName;
  final String? clanName;
  final String? branchName;

  /// Set when this record was merged away. The old id keeps resolving — a
  /// shared link should not rot because two records turned out to be one
  /// person — but the profile says where the person actually lives now.
  final String? mergedIntoUlid;

  String get ulid => summary.ulid;
  String get displayName => summary.displayName;
  bool get isMerged => mergedIntoUlid != null;

  factory PersonDetail.fromJson(Map<String, dynamic> json) {
    String? placeName(Object? raw) =>
        raw is Map ? raw['name'] as String? : null;

    return PersonDetail(
      summary: PersonSummary.fromJson(json),
      biography: json['biography'] as String?,
      birthPlace: placeName(json['birth_place']),
      deathPlace: placeName(json['death_place']),
      tribeName: (json['tribe'] as Map?)?['name'] as String?,
      clanName: (json['clan'] as Map?)?['name'] as String?,
      branchName: (json['family_branch'] as Map?)?['name'] as String?,
      mergedIntoUlid: (json['merged_into'] as Map?)?['ulid'] as String?,
    );
  }
}
