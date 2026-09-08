import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/tree_graph.dart';
import '../../../models/tree_summary.dart';
import '../layout/tree_layout.dart';
import 'chart_caption.dart';
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

  double get _captionHeight =>
      40 + ChartCaption.lines(graph: graph, summary: summary).length * 20;

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
/// The lines come from [ChartCaption], which the document export reads too, so
/// the two can never quote different numbers for the same family.
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

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: theme.textTheme.headlineSmall),
        const SizedBox(height: 4),
        for (final line in ChartCaption.lines(graph: graph, summary: summary))
          Text(
            line.text,
            style: line.emphasis
                ? theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  )
                : quiet,
          ),
      ],
    );
  }
}
