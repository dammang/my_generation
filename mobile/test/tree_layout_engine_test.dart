import 'dart:ui';

import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/layout/tree_layout_engine.dart';
import 'package:my_generation/features/tree/layout/tree_metrics.dart';
import 'package:my_generation/models/person_summary.dart';
import 'package:my_generation/models/tree_graph.dart';

/// Layout is the part most likely to be wrong in a way nobody notices — a
/// sibling drawn under the wrong couple still looks like a family tree. Being a
/// pure function of the graph is what makes it testable at all.

PersonSummary _person(String ulid, int depth, {String? name}) => PersonSummary(
      ulid: ulid,
      displayName: name ?? ulid,
      gender: 'unknown',
      isLiving: false,
      redacted: false,
      depth: depth,
    );

TreeGraph _graph({
  required String focus,
  required Map<String, int> depths,
  List<TreeUnion> unions = const [],
  List<TreeEdge> edges = const [],
  Map<String, Expandable> expandable = const {},
}) =>
    TreeGraph(
      focusUlid: focus,
      people: {
        for (final entry in depths.entries) entry.key: _person(entry.key, entry.value),
      },
      unions: unions,
      edges: edges,
      expandable: expandable,
    );

const engine = TreeLayoutEngine();
const metrics = TreeMetrics();

void main() {
  group('rows', () {
    test('depth becomes the vertical layer', () {
      final layout = engine.layout(_graph(
        focus: 'child',
        depths: {'grandparent': -2, 'parent': -1, 'child': 0, 'grandchild': 1},
        edges: const [
          TreeEdge(parentUlid: 'grandparent', childUlid: 'parent'),
          TreeEdge(parentUlid: 'parent', childUlid: 'child'),
          TreeEdge(parentUlid: 'child', childUlid: 'grandchild'),
        ],
      ));

      final y = {
        for (final node in layout.nodes.values) node.ulid: node.rect.top,
      };

      expect(y['grandparent']! < y['parent']!, isTrue);
      expect(y['parent']! < y['child']!, isTrue);
      expect(y['child']! < y['grandchild']!, isTrue);
    });

    test('rows are one pitch apart', () {
      final layout = engine.layout(_graph(
        focus: 'a',
        depths: {'a': 0, 'b': 1},
        edges: const [TreeEdge(parentUlid: 'a', childUlid: 'b')],
      ));

      final gap = layout.nodes['b']!.rect.top - layout.nodes['a']!.rect.top;

      expect(gap, metrics.rowPitch);
    });

    test('people at the same depth share a row', () {
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0, 'a': 1, 'b': 1, 'c': 1},
        edges: const [
          TreeEdge(parentUlid: 'p', childUlid: 'a'),
          TreeEdge(parentUlid: 'p', childUlid: 'b'),
          TreeEdge(parentUlid: 'p', childUlid: 'c'),
        ],
      ));

      final tops = ['a', 'b', 'c'].map((u) => layout.nodes[u]!.rect.top).toSet();

      expect(tops.length, 1, reason: 'Siblings sit on one line');
    });
  });

  group('couples', () {
    test('partners end up adjacent', () {
      // Ordering must never split a couple, whatever the crossing reduction
      // would otherwise prefer.
      final layout = engine.layout(_graph(
        focus: 'husband',
        depths: {'husband': 0, 'wife': 0, 'other': 0, 'child': 1},
        unions: const [
          TreeUnion(ulid: 'u1', partnerUlids: ['husband', 'wife'], childUlids: ['child']),
        ],
        edges: const [
          TreeEdge(parentUlid: 'husband', childUlid: 'child'),
          TreeEdge(parentUlid: 'wife', childUlid: 'child'),
        ],
      ));

      final husband = layout.nodes['husband']!.rect;
      final wife = layout.nodes['wife']!.rect;
      final other = layout.nodes['other']!.rect;

      final coupleGap = (husband.center.dx - wife.center.dx).abs();
      final toOther = [
        (husband.center.dx - other.center.dx).abs(),
        (wife.center.dx - other.center.dx).abs(),
      ].reduce((a, b) => a < b ? a : b);

      expect(coupleGap < toOther, isTrue, reason: 'A couple sits closer together than to anybody else');
    });

    test('a couple gets a bar between them', () {
      final layout = engine.layout(_graph(
        focus: 'a',
        depths: {'a': 0, 'b': 0},
        unions: const [TreeUnion(ulid: 'u1', partnerUlids: ['a', 'b'], childUlids: [])],
      ));

      expect(layout.unionShapes.single.partnerBar, isNotNull);
    });

    test('a single parent gets no bar', () {
      // There is nobody to join them to, and a bar would imply a partner
      // nobody recorded.
      final layout = engine.layout(_graph(
        focus: 'a',
        depths: {'a': 0, 'child': 1},
        unions: const [TreeUnion(ulid: 'u1', partnerUlids: ['a'], childUlids: ['child'])],
        edges: const [TreeEdge(parentUlid: 'a', childUlid: 'child')],
      ));

      expect(layout.unionShapes.single.partnerBar, isNull);
      expect(layout.unionShapes.single.childDrops, hasLength(1));
    });
  });

  group('children', () {
    test('a parent is centred over their children', () {
      final layout = engine.layout(_graph(
        focus: 'parent',
        depths: {'parent': 0, 'a': 1, 'b': 1, 'c': 1},
        unions: const [
          TreeUnion(ulid: 'u1', partnerUlids: ['parent'], childUlids: ['a', 'b', 'c']),
        ],
        edges: const [
          TreeEdge(parentUlid: 'parent', childUlid: 'a'),
          TreeEdge(parentUlid: 'parent', childUlid: 'b'),
          TreeEdge(parentUlid: 'parent', childUlid: 'c'),
        ],
      ));

      final parent = layout.nodes['parent']!.rect.center.dx;
      final children = ['a', 'b', 'c'].map((u) => layout.nodes[u]!.rect.center.dx).toList()..sort();
      final middle = (children.first + children.last) / 2;

      expect((parent - middle).abs() < 1.0, isTrue,
          reason: 'Centring is what makes a family read as a family');
    });

    test('children get a sibling bar and one drop each', () {
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0, 'a': 1, 'b': 1},
        unions: const [TreeUnion(ulid: 'u1', partnerUlids: ['p'], childUlids: ['a', 'b'])],
        edges: const [
          TreeEdge(parentUlid: 'p', childUlid: 'a'),
          TreeEdge(parentUlid: 'p', childUlid: 'b'),
        ],
      ));

      final shape = layout.unionShapes.single;

      expect(shape.siblingBar, isNotNull);
      expect(shape.childDrops, hasLength(2));
      expect(shape.childDrops.map((d) => d.childUlid), containsAll(['a', 'b']));
    });

    test('an only child gets no sibling bar', () {
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0, 'a': 1},
        unions: const [TreeUnion(ulid: 'u1', partnerUlids: ['p'], childUlids: ['a'])],
        edges: const [TreeEdge(parentUlid: 'p', childUlid: 'a')],
      ));

      expect(layout.unionShapes.single.siblingBar, isNull,
          reason: 'A zero-width bar is a smudge, not information');
    });

    test('children keep the birth order the server sent', () {
      // Birth order is a recorded fact, not something to re-derive.
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0, 'first': 1, 'second': 1, 'third': 1},
        unions: const [
          TreeUnion(ulid: 'u1', partnerUlids: ['p'], childUlids: ['first', 'second', 'third']),
        ],
        edges: const [
          TreeEdge(parentUlid: 'p', childUlid: 'first'),
          TreeEdge(parentUlid: 'p', childUlid: 'second'),
          TreeEdge(parentUlid: 'p', childUlid: 'third'),
        ],
      ));

      final drops = layout.unionShapes.single.childDrops;

      expect(drops.map((d) => d.childUlid).toList(), ['first', 'second', 'third']);
    });
  });

  group('edges', () {
    test('an adoptive child is marked for a dashed drop', () {
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0, 'adopted': 1},
        unions: const [TreeUnion(ulid: 'u1', partnerUlids: ['p'], childUlids: ['adopted'])],
        edges: const [
          TreeEdge(parentUlid: 'p', childUlid: 'adopted', kind: 'adoptive', dashed: true),
        ],
      ));

      expect(layout.unionShapes.single.dashedChildUlids, contains('adopted'));
    });

    test('a parent-child link with no union is still drawn', () {
      // An incomplete oral genealogy looks exactly like this; leaving it
      // undrawn would hide a real relationship.
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0, 'c': 1},
        edges: const [TreeEdge(parentUlid: 'p', childUlid: 'c')],
      ));

      expect(layout.unionShapes, isEmpty);
      expect(layout.looseEdges, hasLength(1));
    });

    test('an edge already drawn by a union is not drawn twice', () {
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0, 'c': 1},
        unions: const [TreeUnion(ulid: 'u1', partnerUlids: ['p'], childUlids: ['c'])],
        edges: const [TreeEdge(parentUlid: 'p', childUlid: 'c')],
      ));

      expect(layout.looseEdges, isEmpty);
    });
  });

  group('canvas', () {
    test('nothing is positioned off the left or top edge', () {
      final layout = engine.layout(_graph(
        focus: 'child',
        depths: {'gp1': -1, 'gp2': -1, 'child': 0},
        edges: const [
          TreeEdge(parentUlid: 'gp1', childUlid: 'child'),
          TreeEdge(parentUlid: 'gp2', childUlid: 'child'),
        ],
      ));

      for (final node in layout.nodes.values) {
        expect(node.rect.left >= 0, isTrue);
        expect(node.rect.top >= 0, isTrue);
      }
    });

    test('the canvas contains every node', () {
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0, 'a': 1, 'b': 1, 'c': 1, 'd': 1},
        edges: const [
          TreeEdge(parentUlid: 'p', childUlid: 'a'),
          TreeEdge(parentUlid: 'p', childUlid: 'b'),
          TreeEdge(parentUlid: 'p', childUlid: 'c'),
          TreeEdge(parentUlid: 'p', childUlid: 'd'),
        ],
      ));

      for (final node in layout.nodes.values) {
        expect(node.rect.right <= layout.canvasSize.width, isTrue);
        expect(node.rect.bottom <= layout.canvasSize.height, isTrue);
      }
    });

    test('the focus rect is reported so the view can open centred on it', () {
      final layout = engine.layout(_graph(focus: 'me', depths: {'me': 0}));

      expect(layout.focusRect, layout.nodes['me']!.rect);
    });

    test('viewport culling returns only what overlaps', () {
      // A tree can carry several hundred people; building a widget for each
      // defeats the point of a canvas.
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0, for (var i = 0; i < 20; i++) 'c$i': 1},
        edges: [for (var i = 0; i < 20; i++) TreeEdge(parentUlid: 'p', childUlid: 'c$i')],
      ));

      final visible = layout.nodesIn(Rect.fromLTWH(0, 0, 400, 400)).toList();

      expect(visible.length < layout.nodes.length, isTrue);
      expect(visible, isNotEmpty);
    });
  });

  group('robustness', () {
    test('an empty graph lays out to nothing rather than throwing', () {
      expect(engine.layout(TreeGraph.empty).isEmpty, isTrue);
    });

    test('a lone person is a valid tree', () {
      final layout = engine.layout(_graph(focus: 'alone', depths: {'alone': 0}));

      expect(layout.nodes, hasLength(1));
      expect(layout.canvasSize.width > 0, isTrue);
    });

    test('a union naming somebody outside the graph does not throw', () {
      // Truncation drops the furthest nodes, so a union can genuinely reference
      // a child who did not fit within the budget.
      final layout = engine.layout(_graph(
        focus: 'p',
        depths: {'p': 0},
        unions: const [
          TreeUnion(ulid: 'u1', partnerUlids: ['p'], childUlids: ['not-returned']),
        ],
      ));

      expect(layout.unionShapes.single.childDrops, isEmpty);
    });

    test('the same graph always lays out identically', () {
      final graph = _graph(
        focus: 'p',
        depths: {'p': 0, 'a': 1, 'b': 1},
        edges: const [
          TreeEdge(parentUlid: 'p', childUlid: 'a'),
          TreeEdge(parentUlid: 'p', childUlid: 'b'),
        ],
      );

      final first = engine.layout(graph);
      final second = engine.layout(graph);

      for (final ulid in first.nodes.keys) {
        expect(first.nodes[ulid]!.rect, second.nodes[ulid]!.rect,
            reason: 'A tree that jumps between identical loads looks broken');
      }
    });
  });
}
