import 'person_summary.dart';

/// How many people descend from somebody, at each remove.
class Descendants {
  const Descendants({
    required this.depth,
    required this.male,
    required this.female,
    required this.unknown,
    required this.total,
  });

  /// 1 is their own children, 2 their grandchildren, and so on.
  final int depth;
  final int male;
  final int female;
  final int unknown;
  final int total;

  /// "7 sons, 2 daughters" — how a family says it out loud.
  ///
  /// Falls back to a plain count where the sex of some of them was never
  /// recorded, which is most of an oral archive: "5 sons" would be an
  /// invention, and this exists to be quoted years later.
  String get describe {
    if (depth > 4) {
      // Past great-great there is no word anybody reads, so the remove is
      // said in numbers instead.
      return '${_people(total)} $depth generations down';
    }

    if (male == 0 && female == 0) return _people(total);

    return [
      if (male > 0) '$male ${_plural('son', male)}',
      if (female > 0) '$female ${_plural('daughter', female)}',
      if (unknown > 0) '$unknown more',
    ].join(', ');
  }

  /// "7 sons, 2 daughters", then "31 grandchildren" — the card has one line
  /// where the exported picture has five, so past the children the sexes are
  /// added together rather than dropped.
  String get shortly {
    if (depth == 1) return describe;

    return '$total ${_plural('child', total).replaceAll('childs', 'children')}';
  }

  String _plural(String word, int count) =>
      '$_prefix$word${count == 1 ? '' : 's'}';

  static String _people(int count) => count == 1 ? '1 person' : '$count people';

  String get _prefix => switch (depth) {
    2 => 'grand',
    3 => 'great-grand',
    4 => 'great-great-grand',
    _ => '',
  };

  factory Descendants.fromJson(Map<String, dynamic> json) => Descendants(
    depth: json['depth'] as int? ?? 0,
    male: json['male'] as int? ?? 0,
    female: json['female'] as int? ?? 0,
    unknown: json['unknown'] as int? ?? 0,
    total: json['total'] as int? ?? 0,
  );
}

/// Everything a chart's caption says about one person.
class TreeSummary {
  const TreeSummary({
    required this.person,
    required this.generations,
    required this.total,
    required this.hidden,
  });

  final PersonSummary person;

  /// One entry per remove, nearest first.
  final List<Descendants> generations;

  /// Everybody descended from them, however far.
  final int total;

  /// How many of those this reader may not see. Said rather than silently
  /// subtracted: a count that quietly excludes them disagrees with the one
  /// their cousin gets.
  final int hidden;

  /// "7 sons, 2 daughters · 31 grandchildren · 83 descendants"
  ///
  /// Two removes and the total. A card that listed every generation would push
  /// the chart off the screen, which is the thing the card is describing.
  String? get shortly {
    if (generations.isEmpty) return null;

    final parts = [
      for (final generation in generations.take(2)) generation.shortly,
      if (total > 0) '$total descendants',
    ];

    return parts.join(' · ');
  }

  factory TreeSummary.fromJson(Map<String, dynamic> json) => TreeSummary(
    person: PersonSummary.fromJson(
      (json['person'] as Map).cast<String, dynamic>(),
    ),
    generations: ((json['generations'] as List?) ?? const [])
        .whereType<Map>()
        .map((g) => Descendants.fromJson(g.cast<String, dynamic>()))
        .toList(growable: false),
    total: json['total'] as int? ?? 0,
    hidden: json['hidden'] as int? ?? 0,
  );
}
