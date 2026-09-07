import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/tree_graph.dart';
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
    this.padding = 64,
  });

  final TreeGraph graph;
  final TreeLayout layout;

  /// Whose tree this is, printed on it. A chart with no caption is a chart
  /// nobody can file.
  final String title;

  final double padding;

  Size get size => Size(
    layout.canvasSize.width + padding * 2,
    layout.canvasSize.height + padding * 2 + _captionHeight,
  );

  static const double _captionHeight = 72;

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
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: theme.textTheme.headlineSmall),
                      const SizedBox(height: 4),
                      Text(
                        // What the picture is of, and what it is not: an
                        // export of a fetched window looks exactly like an
                        // export of a whole family.
                        '${graph.clanPeople} people · '
                        '${graph.clanAbove} up, ${graph.clanBelow} down'
                        '${graph.truncated ? ' · showing the nearest' : ''}',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
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
