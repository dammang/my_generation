import '../../../models/person_summary.dart';

/// One row of a lineage: the father's line and the mother's, side by side.
///
/// A row is a distance above the person the lineage ends with, so the two
/// sides line up by how far back they are, not by their numbers — his father
/// sits beside her, his grandfather beside her father. The two lines usually
/// count from different founders, and matching on the numbers would put a
/// grandfather beside a great-aunt.
class LineageRow {
  const LineageRow({this.person, this.mothers});

  /// The father's line. Null above the top of it, when her line goes back
  /// further than his.
  final PersonSummary? person;

  /// The mother's line at the same distance back. Null on the last row, which
  /// is the person themselves, and wherever her line is not recorded.
  final PersonSummary? mothers;

  /// Pairs [line] (oldest first, ending with the person) with [mothersLine]
  /// (oldest first, ending with the mother).
  static List<LineageRow> pair(
    List<PersonSummary> line,
    List<PersonSummary> mothersLine,
  ) {
    // The mother stands a generation above the person, beside the father, so
    // her line is one row short of reaching the bottom.
    final rows = line.length > mothersLine.length + 1
        ? line.length
        : mothersLine.length + 1;
    final lineStarts = rows - line.length;
    final mothersStart = rows - mothersLine.length - 1;

    return [
      for (var row = 0; row < rows; row++)
        LineageRow(
          person: row >= lineStarts ? line[row - lineStarts] : null,
          mothers:
              row >= mothersStart && row - mothersStart < mothersLine.length
              ? mothersLine[row - mothersStart]
              : null,
        ),
    ];
  }
}
