import 'dart:math' as math;
import 'dart:ui';

import '../../../models/tree_graph.dart';
import 'tree_layout.dart';
import 'tree_metrics.dart';

/// Turns a genealogy graph into positions on a canvas.
///
/// A pure function of the graph and the metrics — no widgets, no state, no
/// framework. That is deliberate: layout is the part most likely to be wrong in
/// a way nobody notices (a sibling drawn under the wrong couple looks fine),
/// and it can only be tested properly if it can be run without a screen.
///
/// The algorithm is a layered one, in three passes:
///
///   1. Rows by depth, which the server already assigns.
///   2. Families: each couple, with everything descended from them, is measured
///      and then given a block of the canvas to itself.
///   3. Coordinates: within a block, children are laid out in birth order and
///      the couple is centred over them.
///
/// It packs families rather than rows because a row cannot be packed correctly
/// on its own: an earlier branch pushes the next one right, the parent above is
/// already placed and cannot follow, and a grandfather ends up six cards from
/// his own grandchildren.
class TreeLayoutEngine {
  const TreeLayoutEngine({this.metrics = const TreeMetrics()});

  final TreeMetrics metrics;

  TreeLayout layout(TreeGraph graph) {
    if (graph.isEmpty) return TreeLayout.empty;

    final rows = _rowsByDepth(graph);
    final relations = _Relations.from(graph);
    final x = _assignX(graph, relations);

    // Rows are read back in the order the placement produced, so everything
    // downstream — the union bars, the drop lines — sees the same left to
    // right as the chart does.
    for (final row in rows.values) {
      row.sort((a, b) => (x[a] ?? 0).compareTo(x[b] ?? 0));
    }

    return _build(graph, rows, x);
  }

  /// depth → people at that depth.
  Map<int, List<String>> _rowsByDepth(TreeGraph graph) {
    final rows = <int, List<String>>{};

    for (final person in graph.people.values) {
      rows.putIfAbsent(person.depth ?? 0, () => <String>[]).add(person.ulid);
    }

    for (final row in rows.values) {
      // A stable starting order, so the same graph always lays out the same way.
      row.sort();
    }

    return rows;
  }

  /// Horizontal coordinates, by packing whole families rather than rows.
  ///
  /// Row-by-row packing cannot keep a parent over a wide family: an earlier
  /// branch pushes the next one right, the parent is already placed and cannot
  /// follow, and a grandfather ends up six cards away from his own
  /// grandchildren. You have to know how wide a family is before you place
  /// anybody in it.
  ///
  /// So each couple, with everything descended from them, is measured first
  /// and then given a block of the canvas to itself. Nothing from one branch
  /// can land inside another, every parent sits over the middle of their own
  /// children, and birth order decides the order of the branches.
  Map<String, double> _assignX(TreeGraph graph, _Relations relations) {
    final families = _Families.from(graph, relations, metrics);

    return families.place();
  }

  TreeLayout _build(
    TreeGraph graph,
    Map<int, List<String>> rows,
    Map<String, double> x,
  ) {
    final depths = rows.keys.toList()..sort();
    final minDepth = depths.first;

    final minX = x.values.reduce(math.min);
    final pad = metrics.horizontalGap;

    final nodes = <String, NodeBox>{};

    for (final entry in rows.entries) {
      final y = (entry.key - minDepth) * metrics.rowPitch + pad;

      for (final ulid in entry.value) {
        nodes[ulid] = NodeBox(
          ulid: ulid,
          rect: Rect.fromLTWH(
            x[ulid]! - minX + pad,
            y,
            metrics.cardWidth,
            metrics.cardHeight,
          ),
          depth: entry.key,
        );
      }
    }

    final unionShapes = _unionShapes(graph, nodes);
    final looseEdges = _looseEdges(graph, nodes);

    final right = nodes.values.map((n) => n.rect.right).reduce(math.max);
    final bottom = nodes.values.map((n) => n.rect.bottom).reduce(math.max);

    return TreeLayout(
      nodes: nodes,
      unionShapes: unionShapes,
      looseEdges: looseEdges,
      canvasSize: Size(right + pad, bottom + pad),
      focusRect: nodes[graph.focusUlid]?.rect ?? Rect.zero,
    );
  }

  List<UnionShape> _unionShapes(TreeGraph graph, Map<String, NodeBox> nodes) {
    final shapes = <UnionShape>[];

    final dashedPairs = {
      for (final edge in graph.edges.where((e) => e.dashed))
        '${edge.parentUlid}|${edge.childUlid}',
    };

    for (final union in graph.unions) {
      final partners = union.partnerUlids
          .map((p) => nodes[p])
          .whereType<NodeBox>()
          .toList();
      final children = union.childUlids
          .map((c) => nodes[c])
          .whereType<NodeBox>()
          .toList();

      if (partners.isEmpty) continue;

      // The point the drop starts from: between the partners, or below a lone
      // parent.
      final junctionX = partners.length >= 2
          ? (partners.first.rect.center.dx + partners.last.rect.center.dx) / 2
          : partners.first.rect.center.dx;

      final partnerBottom = partners.map((p) => p.rect.bottom).reduce(math.max);

      Rect? partnerBar;

      if (partners.length >= 2) {
        final left = partners.map((p) => p.rect.right).reduce(math.min);
        final right = partners.map((p) => p.rect.left).reduce(math.max);
        final y = partners
            .map((p) => p.rect.center.dy)
            .reduce((a, b) => (a + b) / 2);

        if (right > left) {
          partnerBar = Rect.fromLTRB(left, y, right, y);
        }
      }

      if (children.isEmpty) {
        shapes.add(
          UnionShape(
            unionUlid: union.ulid,
            partnerBar: partnerBar,
            junction: Offset(junctionX, partnerBottom),
            siblingBar: null,
            childDrops: const [],
            dashedChildUlids: const {},
          ),
        );
        continue;
      }

      final barY = partnerBottom + metrics.siblingBarOffset;
      final childCentres = children.map((c) => c.rect.center.dx).toList()
        ..sort();

      // One child needs no bar; a zero-width bar is just a smudge.
      final siblingBar = children.length > 1
          ? Rect.fromLTRB(childCentres.first, barY, childCentres.last, barY)
          : null;

      shapes.add(
        UnionShape(
          unionUlid: union.ulid,
          partnerBar: partnerBar,
          junction: Offset(junctionX, partnerBottom),
          siblingBar: siblingBar,
          childDrops: [
            for (final child in children)
              (
                from: Offset(child.rect.center.dx, barY),
                to: child.topCentre,
                childUlid: child.ulid,
              ),
          ],
          dashedChildUlids: {
            for (final child in children)
              if (union.partnerUlids.any(
                (p) => dashedPairs.contains('$p|${child.ulid}'),
              ))
                child.ulid,
          },
        ),
      );
    }

    return shapes;
  }

  /// Parent-child lines with no union behind them.
  ///
  /// These are not an edge case to tidy away: a relationship recorded without
  /// a union is exactly what an incomplete oral genealogy looks like, and
  /// leaving it undrawn would hide a real link.
  List<LooseEdge> _looseEdges(TreeGraph graph, Map<String, NodeBox> nodes) {
    final drawnByUnion = <String>{};

    for (final union in graph.unions) {
      for (final partner in union.partnerUlids) {
        for (final child in union.childUlids) {
          drawnByUnion.add('$partner|$child');
        }
      }
    }

    return [
      for (final edge in graph.edges)
        if (!drawnByUnion.contains('${edge.parentUlid}|${edge.childUlid}'))
          if (nodes[edge.parentUlid] != null && nodes[edge.childUlid] != null)
            LooseEdge(
              from: nodes[edge.parentUlid]!.bottomCentre,
              to: nodes[edge.childUlid]!.topCentre,
              dashed: edge.dashed,
            ),
    ];
  }
}

/// Adjacency, built once per layout instead of scanned per lookup.
class _Relations {
  _Relations(
    this._parents,
    this._children,
    this._partners,
    this._birthRank,
    this._siblingGroup,
  );

  final Map<String, List<String>> _parents;
  final Map<String, List<String>> _children;
  final Map<String, List<String>> _partners;

  /// Where each child comes among their siblings.
  ///
  /// Taken from the order the server lists a union's children in, which is
  /// birth order — the one thing about a row of siblings that everybody in the
  /// family already knows, and the first thing they notice when it is wrong.
  final Map<String, int> _birthRank;

  /// child ulid → the union they are a child of.
  final Map<String, String> _siblingGroup;

  List<String> parentsOf(String ulid) => _parents[ulid] ?? const [];

  List<String> childrenOf(String ulid) => _children[ulid] ?? const [];

  List<String> partnersOf(String ulid) => _partners[ulid] ?? const [];

  int? birthRankOf(String ulid) => _birthRank[ulid];

  /// The union a child belongs to, which is the group birth order applies
  /// within. Children of different marriages are different families and are
  /// ordered against each other by the sweep, not by birth.
  String? siblingGroupOf(String ulid) => _siblingGroup[ulid];

  /// Children eldest first, so a walk that places them lays them out that way.
  List<String> childrenInBirthOrder(String ulid) {
    final children = [...childrenOf(ulid)];

    children.sort((a, b) {
      final ra = _birthRank[a];
      final rb = _birthRank[b];

      if (ra == null || rb == null) return a.compareTo(b);

      return ra == rb ? a.compareTo(b) : ra.compareTo(rb);
    });

    return children;
  }

  factory _Relations.from(TreeGraph graph) {
    final parents = <String, List<String>>{};
    final children = <String, List<String>>{};
    final partners = <String, List<String>>{};
    final birthRank = <String, int>{};
    final siblingGroup = <String, String>{};

    for (final edge in graph.edges) {
      parents.putIfAbsent(edge.childUlid, () => []).add(edge.parentUlid);
      children.putIfAbsent(edge.parentUlid, () => []).add(edge.childUlid);
    }

    for (final union in graph.unions) {
      for (final a in union.partnerUlids) {
        for (final b in union.partnerUlids) {
          if (a != b) partners.putIfAbsent(a, () => []).add(b);
        }
      }

      // First union wins for a child recorded under two, which is rare and
      // means the archive disagrees with itself about who raised them.
      for (var i = 0; i < union.childUlids.length; i++) {
        birthRank.putIfAbsent(union.childUlids[i], () => i);
        siblingGroup.putIfAbsent(union.childUlids[i], () => union.ulid);
      }
    }

    return _Relations(parents, children, partners, birthRank, siblingGroup);
  }
}

/// Every couple in the graph, and what descends from each of them.
///
/// The unit is the couple rather than the person: a husband and wife are drawn
/// side by side and their family hangs beneath both, so they are placed as one
/// thing or they are placed wrong. A man with two wives is one unit of three.
class _Families {
  _Families._(this._metrics, this._blocks, this._children, this._roots);

  final TreeMetrics _metrics;

  /// Each couple, members in the order they should be drawn.
  final List<List<String>> _blocks;

  /// couple → the couples descended from it, already in birth order.
  final Map<int, List<int>> _children;

  /// Couples with nobody above them in this graph.
  final List<int> _roots;

  factory _Families.from(
    TreeGraph graph,
    _Relations relations,
    TreeMetrics metrics,
  ) {
    final order = graph.people.keys.toList();
    final position = {for (var i = 0; i < order.length; i++) order[i]: i};

    // Couples first: the connected components of "is married to".
    final blocks = <List<String>>[];
    final of = <String, int>{};

    for (final ulid in order) {
      if (of.containsKey(ulid)) continue;

      final block = <String>[ulid];
      final queue = <String>[ulid];
      of[ulid] = blocks.length;

      while (queue.isNotEmpty) {
        for (final partner in relations.partnersOf(queue.removeLast())) {
          if (graph.people.containsKey(partner) && !of.containsKey(partner)) {
            of[partner] = blocks.length;
            block.add(partner);
            queue.add(partner);
          }
        }
      }

      blocks.add(_asPath(block, relations, position));
    }

    // Then who descends from whom. One parent only, so this is a forest and
    // every family owns exactly one stretch of the canvas; the other parent's
    // line is still drawn, it simply does not decide the placement.
    final children = <int, List<int>>{};
    final parentOf = <int, int>{};

    for (var i = 0; i < blocks.length; i++) {
      for (final member in blocks[i]) {
        final parent = relations
            .parentsOf(member)
            .where(of.containsKey)
            .map((p) => of[p]!)
            .where((b) => b != i)
            .firstOrNull;

        if (parent != null && !parentOf.containsKey(i)) {
          parentOf[i] = parent;
          children.putIfAbsent(parent, () => []).add(i);
          break;
        }
      }
    }

    // Birth order decides the order of the branches, because it is the order
    // the family itself uses to name them.
    int rank(int block) =>
        blocks[block]
            .map(relations.birthRankOf)
            .firstWhere((r) => r != null, orElse: () => null) ??
        1 << 20;

    for (final list in children.values) {
      list.sort((a, b) {
        final byBirth = rank(a).compareTo(rank(b));

        return byBirth != 0
            ? byBirth
            : blocks[a].first.compareTo(blocks[b].first);
      });
    }

    final roots = [
      for (var i = 0; i < blocks.length; i++)
        if (!parentOf.containsKey(i)) i,
    ];

    return _Families._(metrics, blocks, children, roots);
  }

  /// Orders one couple so that everybody stands next to somebody they married.
  ///
  /// A man with two wives is three people in one block, and laying them out in
  /// the order they arrived puts him at one end — so one of his two marriages
  /// is drawn between two people who never married each other. Walking the
  /// partner links instead puts him in the middle, where he belongs.
  static List<String> _asPath(
    List<String> block,
    _Relations relations,
    Map<String, int> position,
  ) {
    if (block.length < 3) {
      return block..sort((a, b) => position[a]!.compareTo(position[b]!));
    }

    final members = block.toSet();

    int links(String ulid) =>
        relations.partnersOf(ulid).where(members.contains).length;

    // Start at somebody with the fewest marriages inside the block — an end of
    // the chain rather than its middle.
    final remaining = [...block]
      ..sort((a, b) {
        final byLinks = links(a).compareTo(links(b));

        return byLinks != 0 ? byLinks : position[a]!.compareTo(position[b]!);
      });

    final path = <String>[remaining.removeAt(0)];

    while (remaining.isNotEmpty) {
      final next =
          remaining
              .where((m) => relations.partnersOf(path.last).contains(m))
              .firstOrNull ??
          remaining.first;

      path.add(next);
      remaining.remove(next);
    }

    return path;
  }

  /// The x of every person, measured family by family.
  Map<String, double> place() {
    final widths = <int, double>{};

    for (final root in _roots) {
      _measure(root, widths, <int>{});
    }

    final x = <String, double>{};
    var cursor = 0.0;

    for (final root in _roots) {
      _lay(root, cursor, widths, x, <int>{});
      cursor += widths[root]! + _metrics.horizontalGap;
    }

    return x;
  }

  /// How wide a family is: its own couple, or everything beneath it.
  double _measure(int block, Map<int, double> widths, Set<int> seen) {
    if (widths.containsKey(block)) return widths[block]!;
    if (!seen.add(block)) return _own(block);

    final below = _children[block] ?? const <int>[];

    var beneath = 0.0;

    for (final child in below) {
      beneath += _measure(child, widths, seen) + _metrics.horizontalGap;
    }

    if (below.isNotEmpty) beneath -= _metrics.horizontalGap;

    return widths[block] = math.max(_own(block), beneath);
  }

  /// The couple's own width, partners side by side.
  double _own(int block) {
    final members = _blocks[block].length;

    return members * _metrics.cardWidth + (members - 1) * _metrics.partnerGap;
  }

  void _lay(
    int block,
    double left,
    Map<int, double> widths,
    Map<String, double> x,
    Set<int> seen,
  ) {
    if (!seen.add(block)) return;

    final width = widths[block] ?? _own(block);
    final below = _children[block] ?? const <int>[];

    var beneath = 0.0;

    for (final child in below) {
      beneath += (widths[child] ?? _own(child)) + _metrics.horizontalGap;
    }

    if (below.isNotEmpty) beneath -= _metrics.horizontalGap;

    var cursor = left + (width - beneath) / 2;

    for (final child in below) {
      _lay(child, cursor, widths, x, seen);
      cursor += (widths[child] ?? _own(child)) + _metrics.horizontalGap;
    }

    // The couple sits over the middle of what is beneath them, which is what
    // makes a family look like a family rather than a correct grid.
    var at = left + (width - _own(block)) / 2;

    for (final member in _blocks[block]) {
      x[member] = at;
      at += _metrics.cardWidth + _metrics.partnerGap;
    }
  }
}
