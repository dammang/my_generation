import 'package:flutter/material.dart';

import '../../../models/tree_graph.dart';
import '../layout/tree_layout.dart';
import '../widgets/tree_painter.dart';
import '../widgets/tree_person_card.dart';

/// The chart itself: connectors painted underneath, people as widgets on top.
///
/// Only nodes inside the viewport (plus a margin) are built. A tree can carry
/// several hundred people, and building a widget for every one of them would
/// make panning stutter on exactly the devices this app is for.
class TreeCanvas extends StatefulWidget {
  const TreeCanvas({
    super.key,
    required this.graph,
    required this.layout,
    required this.controller,
    required this.onPersonTap,
    required this.onExpand,
  });

  final TreeGraph graph;
  final TreeLayout layout;
  final TransformationController controller;
  final void Function(String ulid) onPersonTap;
  final void Function(String ulid, bool ancestors) onExpand;

  @override
  State<TreeCanvas> createState() => _TreeCanvasState();
}

class _TreeCanvasState extends State<TreeCanvas> {
  /// Built beyond the visible edge so a card is never seen popping in.
  static const double _cullMargin = 220;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_onTransform);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_onTransform);
    super.dispose();
  }

  void _onTransform() => setState(() {});

  Rect _viewport(Size viewSize) {
    final matrix = widget.controller.value;
    final scale = matrix.getMaxScaleOnAxis();
    final translation = matrix.getTranslation();

    return Rect.fromLTWH(
      -translation.x / scale - _cullMargin,
      -translation.y / scale - _cullMargin,
      viewSize.width / scale + _cullMargin * 2,
      viewSize.height / scale + _cullMargin * 2,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return LayoutBuilder(
      builder: (context, constraints) {
        final viewport = _viewport(constraints.biggest);
        final visible = widget.layout.nodesIn(viewport).toList();

        return InteractiveViewer(
          transformationController: widget.controller,
          minScale: 0.25,
          maxScale: 2.5,
          // Generous margins so the outermost people can be brought to the
          // middle of the screen rather than pinned against an edge.
          boundaryMargin: const EdgeInsets.all(400),
          constrained: false,
          child: SizedBox(
            width: widget.layout.canvasSize.width,
            height: widget.layout.canvasSize.height,
            child: Stack(
              children: [
                RepaintBoundary(
                  child: CustomPaint(
                    size: widget.layout.canvasSize,
                    painter: TreePainter(
                      layout: widget.layout,
                      lineColour: theme.colorScheme.outline,
                      accentColour: theme.colorScheme.primary,
                      focusUlid: widget.graph.focusUlid,
                    ),
                  ),
                ),
                for (final node in visible) ..._nodeWidgets(node),
              ],
            ),
          ),
        );
      },
    );
  }

  List<Widget> _nodeWidgets(NodeBox node) {
    final person = widget.graph.person(node.ulid);
    if (person == null) return const [];

    final expandable = widget.graph.expandableFor(node.ulid);

    return [
      Positioned.fromRect(
        rect: node.rect,
        child: TreePersonCard(
          person: person,
          isFocus: node.ulid == widget.graph.focusUlid,
          expandable: expandable,
          onTap: () => widget.onPersonTap(node.ulid),
        ),
      ),

      // Affordances sit outside the card, on the side the missing family is on,
      // so which direction they open is obvious without reading them.
      if (expandable.parents > 0)
        Positioned(
          left: node.rect.left,
          top: node.rect.top - 26,
          width: node.rect.width,
          child: Center(
            child: ExpandChip(
              count: expandable.parents,
              ancestors: true,
              onTap: () => widget.onExpand(node.ulid, true),
            ),
          ),
        ),

      if (expandable.children > 0)
        Positioned(
          left: node.rect.left,
          top: node.rect.bottom + 6,
          width: node.rect.width,
          child: Center(
            child: ExpandChip(
              count: expandable.children,
              ancestors: false,
              onTap: () => widget.onExpand(node.ulid, false),
            ),
          ),
        ),
    ];
  }
}
