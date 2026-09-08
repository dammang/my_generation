import '../../../models/tree_graph.dart';
import '../../../models/tree_summary.dart';

/// One line of what a chart says about itself.
class CaptionLine {
  const CaptionLine(this.text, {this.emphasis = false});

  final String text;

  /// The one line somebody quotes: the size of the family.
  final bool emphasis;
}

/// What a chart says about itself, as text.
///
/// Shared by the picture and the document rather than written twice. Two
/// exports of the same family that disagreed about how many descendants it has
/// would be worse than either of them alone, and nothing about a caption
/// written in two places would ever report that they had drifted.
///
/// A family chart is looked at years after it was made, by somebody who was
/// not there when it was drawn. This is what makes it a record rather than a
/// picture of some boxes.
class ChartCaption {
  const ChartCaption._();

  static List<CaptionLine> lines({
    required TreeGraph graph,
    TreeSummary? summary,
  }) {
    final standing =
        summary?.person.generation ?? graph.person(graph.focusUlid)?.generation;
    final descendants = summary?.generations ?? const [];
    final lines = <CaptionLine>[];

    // Where they stand, on both scales the clan keeps.
    if (standing?.outerNumber != null && standing?.outerOrigin != null) {
      lines.add(
        CaptionLine(
          '${ordinal(standing!.outerNumber!)} generation from '
          '${standing.outerOrigin}',
        ),
      );
    }

    if (standing?.number != null && standing?.origin != null) {
      lines.add(
        CaptionLine(
          '${ordinal(standing!.number!)} generation of ${standing.origin}',
        ),
      );
    }

    if (standing?.beforeOrigin != null && standing?.origin != null) {
      lines.add(
        CaptionLine(
          standing!.beforeOrigin == 1
              ? '1 generation before ${standing.origin}'
              : '${standing.beforeOrigin} generations before '
                    '${standing.origin}',
        ),
      );
    }

    // Then the family itself, one line per remove — which is how anybody says
    // it out loud.
    for (final generation in descendants) {
      lines.add(CaptionLine('Had ${generation.describe}'));
    }

    if (summary != null) {
      lines.add(
        CaptionLine(
          summary.total == 0
              ? 'No descendants recorded'
              : '${summary.total} descendants in all'
                    '${summary.hidden > 0 ? ' · ${summary.hidden} not shown to you' : ''}',
          emphasis: true,
        ),
      );
    }

    // What the picture is of, and what it is not: an export of a fetched
    // window looks exactly like an export of a whole family.
    lines.add(
      CaptionLine(
        '${graph.clanPeople} in this clan'
        '${graph.truncated ? ' · chart shows the nearest' : ''}',
      ),
    );

    return lines;
  }

  static String ordinal(int n) => switch (n % 100) {
    11 || 12 || 13 => '${n}th',
    _ => switch (n % 10) {
      1 => '${n}st',
      2 => '${n}nd',
      3 => '${n}rd',
      _ => '${n}th',
    },
  };
}
