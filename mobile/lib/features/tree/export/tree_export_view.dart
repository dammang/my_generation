import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/tree_graph.dart';
import '../../../models/tree_summary.dart';
import '../layout/tree_layout.dart';
import '../widgets/tree_painter.dart';
import '../widgets/tree_person_card.dart';

/// The whole chart, drawn for a picture rather than for a screen.
///
/// The same painter and the same cards the app draws, so an exported chart
/// cannot drift into looking like a different product. What differs is what is
/// left out: nothing is culled, because there is no viewport to cull against,
/// and nothing is tappable, because nobody taps a photograph.
class TreeExportView extends StatelessWidget {
  const TreeExportView({
    super.key,
    required this.graph,
    required this.layout,
    required this.title,
    this.summary,
    this.padding = 64,
  });

  final TreeGraph graph;
  final TreeLayout layout;

  /// Whose tree this is, printed on it. A chart with no caption is a chart
  /// nobody can file.
  final String title;

  /// Counted from the graph rather than from what was fetched. Null when the
  /// count could not be had, and then the caption says what it does know
  /// instead of inventing the rest.
  final TreeSummary? summary;

  final double padding;

  Size get size => Size(
    layout.canvasSize.width + padding * 2,
    layout.canvasSize.height + padding * 2 + _captionHeight,
  );

  double get _captionHeight {
    // One line per remove, plus the two generation lines and the totals.
    final lines = 2 + (summary?.generations.length ?? 0) + 2;

    return 40 + lines * 20;
  }

  @override
  Widget build(BuildContext context) {
    final theme = AppTheme.light();

    return MediaQuery(
      // Fixed, so an export is the same picture whatever the phone's own type
      // size happens to be set to — and so the card metrics the layout was
      // measured with are the ones it is drawn with.
      data: const MediaQueryData(textScaler: TextScaler.noScaling),
      child: Theme(
        data: theme,
        child: Material(
          color: theme.colorScheme.surface,
          child: SizedBox.fromSize(
            size: size,
            child: Stack(
              children: [
                Positioned(
                  left: padding,
                  top: padding + _captionHeight,
                  width: layout.canvasSize.width,
                  height: layout.canvasSize.height,
                  child: Stack(
                    children: [
                      CustomPaint(
                        size: layout.canvasSize,
                        painter: TreePainter(
                          layout: layout,
                          lineColour: theme.colorScheme.outline,
                          accentColour: theme.colorScheme.primary,
                          focusUlid: graph.focusUlid,
                        ),
                      ),
                      for (final node in layout.nodes.values)
                        if (graph.person(node.ulid) case final person?)
                          Positioned.fromRect(
                            rect: node.rect,
                            child: TreePersonCard(
                              person: person,
                              isFocus: node.ulid == graph.focusUlid,
                              expandable: graph.expandableFor(node.ulid),
                            ),
                          ),
                    ],
                  ),
                ),
                Positioned(
                  left: padding,
                  top: padding,
                  right: padding,
                  child: _Caption(
                    title: title,
                    graph: graph,
                    summary: summary,
                    theme: theme,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// What the picture says about itself.
///
/// A family chart is looked at years after it was made, by somebody who was
/// not there when it was drawn. The caption is what makes it a record rather
/// than a picture of some boxes.
class _Caption extends StatelessWidget {
  const _Caption({
    required this.title,
    required this.graph,
    required this.summary,
    required this.theme,
  });

  final String title;
  final TreeGraph graph;
  final TreeSummary? summary;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final quiet = theme.textTheme.bodyMedium?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );

    final standing =
        summary?.person.generation ?? graph.person(graph.focusUlid)?.generation;
    final descendants = summary?.generations ?? const [];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: theme.textTheme.headlineSmall),
        const SizedBox(height: 4),

        // Where they stand, on both scales the clan keeps.
        if (standing?.outerNumber != null && standing?.outerOrigin != null)
          Text(
            '${_ordinal(standing!.outerNumber!)} generation from '
            '${standing.outerOrigin}',
            style: quiet,
          ),
        if (standing?.number != null && standing?.origin != null)
          Text(
            '${_ordinal(standing!.number!)} generation of ${standing.origin}',
            style: quiet,
          ),
        if (standing?.beforeOrigin != null && standing?.origin != null)
          Text(
            standing!.beforeOrigin == 1
                ? '1 generation before ${standing.origin}'
                : '${standing.beforeOrigin} generations before ${standing.origin}',
            style: quiet,
          ),

        if (descendants.isNotEmpty) const SizedBox(height: 6),

        // Then the family itself, one line per remove — which is how anybody
        // says it out loud.
        for (final generation in descendants)
          Text('Had ${generation.describe}', style: quiet),

        if (summary != null) ...[
          const SizedBox(height: 6),
          Text(
            summary!.total == 0
                ? 'No descendants recorded'
                : '${summary!.total} descendants in all'
                      '${summary!.hidden > 0 ? ' · ${summary!.hidden} not shown to you' : ''}',
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
        ],

        // What the picture is of, and what it is not: an export of a fetched
        // window looks exactly like an export of a whole family.
        Text(
          '${graph.clanPeople} in this clan'
          '${graph.truncated ? ' · chart shows the nearest' : ''}',
          style: quiet,
        ),
      ],
    );
  }

  static String _ordinal(int n) => switch (n % 100) {
    11 || 12 || 13 => '${n}th',
    _ => switch (n % 10) {
      1 => '${n}st',
      2 => '${n}nd',
      3 => '${n}rd',
      _ => '${n}th',
    },
  };
}
