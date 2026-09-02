/// A tribe as the onboarding list shows it.
class TribeSummary {
  const TribeSummary({
    required this.ulid,
    required this.name,
    this.nativeName,
    this.shortName,
    this.description,
    this.region,
    this.countryCode,
    this.peopleCount = 0,
    this.clanCount = 0,
  });

  final String ulid;
  final String name;
  final String? nativeName;
  final String? shortName;
  final String? description;
  final String? region;
  final String? countryCode;
  final int peopleCount;
  final int clanCount;

  /// "Chin State, MM · 217 people" — enough to tell two similarly named tribes
  /// apart without opening either.
  String get subtitle {
    final place = [region, countryCode].where((p) => p != null && p.isNotEmpty).join(', ');
    final size = peopleCount == 1 ? '1 person' : '$peopleCount people';

    return place.isEmpty ? size : '$place · $size';
  }

  factory TribeSummary.fromJson(Map<String, dynamic> json) {
    final counts = (json['counts'] as Map?)?.cast<String, dynamic>() ?? const {};

    return TribeSummary(
      ulid: json['ulid'] as String,
      name: json['name'] as String? ?? '',
      nativeName: json['native_name'] as String?,
      shortName: json['short_name'] as String?,
      description: json['description'] as String?,
      region: json['region'] as String?,
      countryCode: json['country_code'] as String?,
      peopleCount: counts['people'] as int? ?? 0,
      clanCount: counts['clans'] as int? ?? 0,
    );
  }
}
