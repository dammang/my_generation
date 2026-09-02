import 'dart:ui';

/// Where one person sits on the canvas.
class NodeBox {
  const NodeBox({required this.ulid, required this.rect, required this.depth});

  final String ulid;
  final Rect rect;

  /// Layer relative to the focus: negative above, positive below.
  final int depth;

  Offset get topCentre => Offset(rect.center.dx, rect.top);
  Offset get bottomCentre => Offset(rect.center.dx, rect.bottom);
}

/// A couple and the drop to their children, drawn as one shape.
///
/// This is what makes the chart read as a family tree rather than a graph: the
/// bar between partners, one line down, a sibling bar, and a drop to each child.
class UnionShape {
  const UnionShape({
    required this.unionUlid,
    required this.partnerBar,
    required this.junction,
    required this.siblingBar,
    required this.childDrops,
    required this.dashedChildUlids,
  });

  /// Between the two partners. Null for a single parent — there is nobody to
  /// join them to, and inventing a bar would imply a partner nobody recorded.
  final Rect? partnerBar;

  final String unionUlid;

  /// Where the drop to the children begins.
  final Offset junction;

  /// The horizontal bar the children hang from. Null when there is one child:
  /// a bar of zero width is just noise.
  final Rect? siblingBar;

  /// One drop per child, from the sibling bar to the child's top edge.
  final List<({Offset from, Offset to, String childUlid})> childDrops;

  /// Children joined by adoption, fostering or a step relationship.
  final Set<String> dashedChildUlids;
}

/// A parent-child line with no union behind it.
class LooseEdge {
  const LooseEdge({required this.from, required this.to, required this.dashed});

  final Offset from;
  final Offset to;
  final bool dashed;
}

/// The finished arrangement: everything the painter and the widget layer need,
/// and nothing about how either of them works.
class TreeLayout {
  const TreeLayout({
    required this.nodes,
    required this.unionShapes,
    required this.looseEdges,
    required this.canvasSize,
    required this.focusRect,
  });

  final Map<String, NodeBox> nodes;
  final List<UnionShape> unionShapes;
  final List<LooseEdge> looseEdges;
  final Size canvasSize;

  /// Where the focus person ended up, so the view can open centred on them.
  final Rect focusRect;

  bool get isEmpty => nodes.isEmpty;

  static const TreeLayout empty = TreeLayout(
    nodes: {},
    unionShapes: [],
    looseEdges: [],
    canvasSize: Size.zero,
    focusRect: Rect.zero,
  );

  /// Nodes intersecting a viewport, for culling.
  ///
  /// A tree can carry several hundred people and building a widget for every
  /// one of them defeats the point of a canvas.
  Iterable<NodeBox> nodesIn(Rect viewport) =>
      nodes.values.where((node) => node.rect.overlaps(viewport));
}
