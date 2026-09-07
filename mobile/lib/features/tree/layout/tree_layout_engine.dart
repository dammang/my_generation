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

      for (final child in relations.childrenInBirthOrder(ulid)) {
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
    var current = {
      for (final entry in rows.entries)
        entry.key: List<String>.from(entry.value),
    };

    for (var sweep = 0; sweep < _orderingSweeps; sweep++) {
      final downward = sweep.isEven;
      final order = downward ? depths : depths.reversed.toList();

      for (final depth in order) {
        final fixedDepth = downward ? depth - 1 : depth + 1;
        final fixed = current[fixedDepth];

        if (fixed == null) continue;

        final index = {
          for (var i = 0; i < fixed.length; i++) fixed[i]: i.toDouble(),
        };
        final row = current[depth]!;

        final keys = <String, double>{};

        for (var i = 0; i < row.length; i++) {
          final ulid = row[i];
          final neighbours = downward
              ? relations.parentsOf(ulid)
              : relations.childrenOf(ulid);
          final positions =
              neighbours.map((n) => index[n]).whereType<double>().toList()
                ..sort();

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

        // Crossing reduction decides which places a family occupies; birth
        // order decides who sits in each of them. Left to itself the sweep
        // reorders siblings by where their own children happen to be, which is
        // how the seventh son ended up second — he was the only one of the
        // seven with a family of his own, so his median pulled him left.
        _seatSiblingsInBirthOrder(row, relations);
      }
    }

    return current;
  }

  /// Puts each family back in birth order without moving where it sits.
  ///
  /// The places a sibling group occupies are left exactly as the sweep left
  /// them — including any gaps where another family's child sits between them —
  /// and only who occupies which place changes. Nothing about the crossing
  /// count can therefore get worse.
  void _seatSiblingsInBirthOrder(List<String> row, _Relations relations) {
    // A married sibling moves with their husband or wife. Seating the people
    // one at a time put a brother in a place whose neighbour was somebody
    // else's wife, and the chart then drew them as a couple — a worse error
    // than the order it was correcting.
    final blocks = _couples(row, relations);

    String? familyOf(List<String> block) => block
        .map(relations.siblingGroupOf)
        .firstWhere((family) => family != null, orElse: () => null);

    int rankOf(List<String> block) =>
        block
            .map(relations.birthRankOf)
            .firstWhere((rank) => rank != null, orElse: () => null) ??
        0;

    final places = <String, List<int>>{};

    for (var i = 0; i < blocks.length; i++) {
      final family = familyOf(blocks[i]);

      if (family != null) places.putIfAbsent(family, () => []).add(i);
    }

    for (final entry in places.entries) {
      if (entry.value.length < 2) continue;

      final ordered = entry.value.map((i) => blocks[i]).toList()
        ..sort((a, b) {
          final byBirth = rankOf(a).compareTo(rankOf(b));

          return byBirth != 0 ? byBirth : a.first.compareTo(b.first);
        });

      for (var k = 0; k < entry.value.length; k++) {
        blocks[entry.value[k]] = ordered[k];
      }
    }

    final seated = blocks.expand((block) => block).toList();

    for (var i = 0; i < row.length; i++) {
      row[i] = seated[i];
    }
  }

  /// The row split into runs of people who are drawn as couples.
  ///
  /// Each run is one thing as far as ordering is concerned: whatever happens to
  /// a married person happens to their partner, or the two are left standing
  /// beside somebody they were never married to.
  List<List<String>> _couples(List<String> row, _Relations relations) {
    final blocks = <List<String>>[];

    for (final ulid in row) {
      final joinsPrevious =
          blocks.isNotEmpty &&
          relations.partnersOf(blocks.last.last).contains(ulid);

      if (joinsPrevious) {
        blocks.last.add(ulid);
      } else {
        blocks.add([ulid]);
      }
    }

    return blocks;
  }

  /// Horizontal coordinates.
  ///
  /// Rows are packed from the deepest upward so parents can be centred over
  /// children that already have positions. Centring is what makes a family look
  /// like a family; without it the chart is a correct but unreadable grid.
  Map<String, double> _assignX(
    Map<int, List<String>> rows,
    _Relations relations,
  ) {
    final depths = rows.keys.toList()..sort();
    final x = <String, double>{};

    // Downward first, so every subtree is planted under the person it belongs
    // to. Going bottom-up alone anchored each deep line at the left edge of the
    // canvas and then dragged its ancestor there to sit over it — a seventh son
    // with a family of his own was pulled to the front of his siblings, and his
    // descendants hung off the wrong side of the chart.
    for (final depth in depths) {
      _packRow(rows[depth]!, relations, x, anchor: relations.parentsOf);
    }

    // Then upward, so a parent sits over the middle of their own children
    // rather than at the left end of them. Clamped between the neighbours
    // already placed, so nobody changes places at this stage.
    for (final depth in depths.reversed) {
      _centreOverChildren(rows[depth]!, relations, x);
    }

    return x;
  }

  /// One row, packed left to right, each person under whoever anchors them.
  void _packRow(
    List<String> row,
    _Relations relations,
    Map<String, double> x, {
    required List<String> Function(String ulid) anchor,
  }) {
    var cursor = 0.0;

    for (var i = 0; i < row.length; i++) {
      final ulid = row[i];
      final above = anchor(ulid).where(x.containsKey).toList();

      // Under the middle of whoever they hang from, or wherever there is room.
      final desired = above.isEmpty
          ? cursor
          : (above.map((a) => x[a]!).reduce(math.min) +
                    above.map((a) => x[a]!).reduce(math.max)) /
                2;

      // Never overlap the neighbour to the left; push right instead of
      // shrinking the gap, so cards keep a consistent size.
      final placed = math.max(desired, cursor);
      x[ulid] = placed;

      cursor = placed + metrics.cardWidth + _gapAfter(row, i, relations);
    }
  }

  /// Pulls each parent over their children, as far as their neighbours allow.
  void _centreOverChildren(
    List<String> row,
    _Relations relations,
    Map<String, double> x,
  ) {
    for (var i = 0; i < row.length; i++) {
      final ulid = row[i];
      final children = relations.childrenOf(ulid).where(x.containsKey).toList();

      if (children.isEmpty) continue;

      final centres = children.map((c) => x[c]!).toList()..sort();
      final desired = (centres.first + centres.last) / 2;

      final leftBound = i == 0
          ? double.negativeInfinity
          : x[row[i - 1]]! +
                metrics.cardWidth +
                _gapAfter(row, i - 1, relations);

      final rightBound = i + 1 < row.length
          ? x[row[i + 1]]! - metrics.cardWidth - _gapAfter(row, i, relations)
          : double.infinity;

      // A parent whose children sit outside the room they have keeps their
      // place: moving would put them past a sibling, which is a worse lie
      // about the family than an off-centre drop line.
      x[ulid] = desired.clamp(leftBound, math.max(leftBound, rightBound));
    }
  }

  /// The narrow gap belongs between two people who are actually a couple, not
  /// after anybody who happens to have a partner somewhere in the row.
  double _gapAfter(List<String> row, int i, _Relations relations) {
    final next = i + 1 < row.length ? row[i + 1] : null;
    final isPartner =
        next != null && relations.partnersOf(row[i]).contains(next);

    return isPartner ? metrics.partnerGap : metrics.horizontalGap;
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

  static double _median(List<double> sorted) {
    if (sorted.isEmpty) return 0;
    final mid = sorted.length ~/ 2;

    return sorted.length.isOdd
        ? sorted[mid]
        : (sorted[mid - 1] + sorted[mid]) / 2;
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
