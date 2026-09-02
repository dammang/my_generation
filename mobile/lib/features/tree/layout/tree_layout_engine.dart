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
///   2. Ordering within each row, sweeping up and down and sorting by the
///      median position of each node's neighbours in the adjacent row. Couples
///      are ordered as one block so partners never end up apart.
///   3. Coordinates: children are packed left to right, then each parent is
///      pulled to sit centred over its own children, with overlaps resolved by
///      pushing right.
class TreeLayoutEngine {
  const TreeLayoutEngine({this.metrics = const TreeMetrics()});

  final TreeMetrics metrics;

  /// How many up-and-down sweeps to spend reducing crossings. Four is enough
  /// for the depths this app allows; more buys nothing measurable.
  static const int _orderingSweeps = 4;

  TreeLayout layout(TreeGraph graph) {
    if (graph.isEmpty) return TreeLayout.empty;

    final rows = _rowsByDepth(graph);
    final relations = _Relations.from(graph);

    var ordered = _initialOrder(graph, rows, relations);
    ordered = _reduceCrossings(ordered, relations);

    final x = _assignX(ordered, relations);

    return _build(graph, ordered, x);
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

  /// A first ordering, walking outward from the focus.
  ///
  /// Starting anywhere else tends to leave the person somebody actually asked
  /// about drifting off to one side.
  Map<int, List<String>> _initialOrder(
    TreeGraph graph,
    Map<int, List<String>> rows,
    _Relations relations,
  ) {
    final seen = <String>{};
    final ordered = {for (final depth in rows.keys) depth: <String>[]};

    void place(String ulid) {
      if (!seen.add(ulid)) return;

      final depth = graph.person(ulid)?.depth ?? 0;
      ordered[depth]?.add(ulid);

      // Partners immediately, so a couple is never split by whatever comes next.
      for (final partner in relations.partnersOf(ulid)) {
        if (graph.people.containsKey(partner)) place(partner);
      }

      for (final child in relations.childrenOf(ulid)) {
        if (graph.people.containsKey(child)) place(child);
      }

      for (final parent in relations.parentsOf(ulid)) {
        if (graph.people.containsKey(parent)) place(parent);
      }
    }

    place(graph.focusUlid);

    // Anybody the walk did not reach — a spouse of a spouse, a detached branch.
    for (final entry in rows.entries) {
      for (final ulid in entry.value) {
        place(ulid);
      }
    }

    return ordered;
  }

  /// Median-based crossing reduction, sweeping down then up.
  Map<int, List<String>> _reduceCrossings(
    Map<int, List<String>> rows,
    _Relations relations,
  ) {
    final depths = rows.keys.toList()..sort();
    var current = {for (final entry in rows.entries) entry.key: List<String>.from(entry.value)};

    for (var sweep = 0; sweep < _orderingSweeps; sweep++) {
      final downward = sweep.isEven;
      final order = downward ? depths : depths.reversed.toList();

      for (final depth in order) {
        final fixedDepth = downward ? depth - 1 : depth + 1;
        final fixed = current[fixedDepth];

        if (fixed == null) continue;

        final index = {for (var i = 0; i < fixed.length; i++) fixed[i]: i.toDouble()};
        final row = current[depth]!;

        final keys = <String, double>{};

        for (var i = 0; i < row.length; i++) {
          final ulid = row[i];
          final neighbours = downward ? relations.parentsOf(ulid) : relations.childrenOf(ulid);
          final positions = neighbours.map((n) => index[n]).whereType<double>().toList()..sort();

          // No neighbour in the fixed row means nothing pulls this node; its
          // current position is as good as any, so it keeps it.
          keys[ulid] = positions.isEmpty ? i.toDouble() : _median(positions);
        }

        // Partners share a key so they sort as one block and stay adjacent.
        for (final ulid in row) {
          final partners = relations.partnersOf(ulid).where(keys.containsKey);

          if (partners.isNotEmpty) {
            final shared = [keys[ulid]!, ...partners.map((p) => keys[p]!)];
            keys[ulid] = shared.reduce(math.min);
          }
        }

        row.sort((a, b) {
          final byKey = keys[a]!.compareTo(keys[b]!);
          return byKey != 0 ? byKey : a.compareTo(b);
        });
      }
    }

    return current;
  }

  /// Horizontal coordinates.
  ///
  /// Rows are packed from the deepest upward so parents can be centred over
  /// children that already have positions. Centring is what makes a family look
  /// like a family; without it the chart is a correct but unreadable grid.
  Map<String, double> _assignX(Map<int, List<String>> rows, _Relations relations) {
    final depths = rows.keys.toList()..sort();
    final x = <String, double>{};

    for (final depth in depths.reversed) {
      final row = rows[depth]!;
      var cursor = 0.0;

      for (var i = 0; i < row.length; i++) {
        final ulid = row[i];
        final children = relations.childrenOf(ulid).where(x.containsKey).toList();

        // Preferred position: over the middle of this person's children.
        double desired;

        if (children.isEmpty) {
          desired = cursor;
        } else {
          final centres = children.map((c) => x[c]!).toList()..sort();
          desired = (centres.first + centres.last) / 2;
        }

        // Never overlap the neighbour to the left; push right instead of
        // shrinking the gap, so cards keep a consistent size.
        final placed = math.max(desired, cursor);
        x[ulid] = placed;

        // The narrow gap belongs between two people who are actually a couple,
        // not after anybody who happens to have a partner somewhere in the row.
        final next = i + 1 < row.length ? row[i + 1] : null;
        final nextIsPartner = next != null && relations.partnersOf(ulid).contains(next);

        cursor = placed +
            metrics.cardWidth +
            (nextIsPartner ? metrics.partnerGap : metrics.horizontalGap);
      }
    }

    return x;
  }

  TreeLayout _build(TreeGraph graph, Map<int, List<String>> rows, Map<String, double> x) {
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
          rect: Rect.fromLTWH(x[ulid]! - minX + pad, y, metrics.cardWidth, metrics.cardHeight),
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
      for (final edge in graph.edges.where((e) => e.dashed)) '${edge.parentUlid}|${edge.childUlid}',
    };

    for (final union in graph.unions) {
      final partners = union.partnerUlids.map((p) => nodes[p]).whereType<NodeBox>().toList();
      final children = union.childUlids.map((c) => nodes[c]).whereType<NodeBox>().toList();

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
        final y = partners.map((p) => p.rect.center.dy).reduce((a, b) => (a + b) / 2);

        if (right > left) {
          partnerBar = Rect.fromLTRB(left, y, right, y);
        }
      }

      if (children.isEmpty) {
        shapes.add(UnionShape(
          unionUlid: union.ulid,
          partnerBar: partnerBar,
          junction: Offset(junctionX, partnerBottom),
          siblingBar: null,
          childDrops: const [],
          dashedChildUlids: const {},
        ));
        continue;
      }

      final barY = partnerBottom + metrics.siblingBarOffset;
      final childCentres = children.map((c) => c.rect.center.dx).toList()..sort();

      // One child needs no bar; a zero-width bar is just a smudge.
      final siblingBar = children.length > 1
          ? Rect.fromLTRB(childCentres.first, barY, childCentres.last, barY)
          : null;

      shapes.add(UnionShape(
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
            if (union.partnerUlids.any((p) => dashedPairs.contains('$p|${child.ulid}')))
              child.ulid,
        },
      ));
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

  static double _median(List<double> sorted) {
    if (sorted.isEmpty) return 0;
    final mid = sorted.length ~/ 2;

    return sorted.length.isOdd ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
  }
}

/// Adjacency, built once per layout instead of scanned per lookup.
class _Relations {
  _Relations(this._parents, this._children, this._partners);

  final Map<String, List<String>> _parents;
  final Map<String, List<String>> _children;
  final Map<String, List<String>> _partners;

  List<String> parentsOf(String ulid) => _parents[ulid] ?? const [];

  List<String> childrenOf(String ulid) => _children[ulid] ?? const [];

  List<String> partnersOf(String ulid) => _partners[ulid] ?? const [];

  factory _Relations.from(TreeGraph graph) {
    final parents = <String, List<String>>{};
    final children = <String, List<String>>{};
    final partners = <String, List<String>>{};

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
    }

    return _Relations(parents, children, partners);
  }
}
