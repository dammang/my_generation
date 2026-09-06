/// A named family line, as the "link a family" search lists them.
class FamilyBranchSummary {
  const FamilyBranchSummary({
    required this.ulid,
    required this.name,
    this.tribeName,
    this.clanName,
    this.ancestorName,
  });

  final String ulid;
  final String name;
  final String? tribeName;
  final String? clanName;

  /// The line's apical ancestor, where one is named. It is what actually tells
  /// two families with the same name apart.
  final String? ancestorName;

  /// "Whitfield · from Edward Whitfield" — enough to choose between two
  /// families called the same thing without opening either.
  String get subtitle {
    final parts = [
      if (clanName != null && clanName!.isNotEmpty) clanName,
      if (tribeName != null && tribeName!.isNotEmpty) tribeName,
      if (ancestorName != null && ancestorName!.isNotEmpty) 'from $ancestorName',
    ];

    return parts.join(' · ');
  }

  factory FamilyBranchSummary.fromJson(Map<String, dynamic> json) {
    final tribe = (json['tribe'] as Map?)?.cast<String, dynamic>();
    final clan = (json['clan'] as Map?)?.cast<String, dynamic>();
    final ancestor = (json['ancestor'] as Map?)?.cast<String, dynamic>();

    return FamilyBranchSummary(
      ulid: json['ulid'] as String,
      name: json['name'] as String? ?? 'Unnamed family',
      tribeName: tribe?['name'] as String?,
      clanName: clan?['name'] as String?,
      ancestorName: ancestor?['display_name'] as String?,
    );
  }
}
