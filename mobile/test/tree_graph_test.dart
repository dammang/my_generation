import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/models/tree_graph.dart';

/// Parsing what the tree endpoint actually returns, including the parts that
/// only appear at the edges of a traversal.
void main() {
  Map<String, dynamic> data({
    List<Map<String, dynamic>> people = const [],
    List<Map<String, dynamic>> unions = const [],
    List<Map<String, dynamic>> edges = const [],
    String focus = '01A',
  }) =>
      {'focus': focus, 'people': people, 'unions': unions, 'edges': edges};

  group('TreeGraph', () {
    test('indexes people by ulid and keeps their depth', () {
      final graph = TreeGraph.fromResponse(
        data(people: [
          {'ulid': '01A', 'display_name': 'Khai Nang', 'gender': 'male', 'is_living': false, 'redacted': false, 'depth': 0},
          {'ulid': '01B', 'display_name': 'Cin Sang', 'gender': 'male', 'is_living': false, 'redacted': false, 'depth': -1},
        ]),
        const {'node_count': 2},
      );

      expect(graph.person('01B')!.depth, -1);
      expect(graph.people, hasLength(2));
    });

    test('reads a union with its children in the order sent', () {
      final graph = TreeGraph.fromResponse(
        data(unions: [
          {
            'ulid': '01U',
            'partners': ['01A', '01B'],
            'children': ['01C', '01D', '01E'],
            'marriage_year': 1893,
            'order_index': 1,
          }
        ]),
        const {},
      );

      final union = graph.unions.single;

      expect(union.partnerUlids, ['01A', '01B']);
      expect(union.childUlids, ['01C', '01D', '01E']);
      expect(union.isSingleParent, isFalse);
      expect(union.marriageYear, 1893);
    });

    test('recognises a single-parent union', () {
      // Real and common in historical records; the chart must not draw a
      // partner bar to somebody nobody recorded.
      final graph = TreeGraph.fromResponse(
        data(unions: [
          {'ulid': '01U', 'partners': ['01A'], 'children': ['01C']}
        ]),
        const {},
      );

      expect(graph.unions.single.isSingleParent, isTrue);
    });

    test('carries the dashed flag the server set', () {
      // Whether a link is drawn dashed is the server's decision — the chart
      // must not silently assert biology it has no evidence for.
      final graph = TreeGraph.fromResponse(
        data(edges: [
          {'parent': '01A', 'child': '01C', 'kind': 'adoptive', 'dashed': true},
          {'parent': '01A', 'child': '01D', 'kind': 'biological', 'dashed': false},
        ]),
        const {},
      );

      expect(graph.edges.first.dashed, isTrue);
      expect(graph.edges.first.kind, 'adoptive');
      expect(graph.edges.last.dashed, isFalse);
    });

    test('reads what was left out, per person', () {
      final graph = TreeGraph.fromResponse(
        data(),
        const {
          'expandable': {
            '01A': {'children': 12, 'parents': 2},
            '01B': {'children': 3},
          }
        },
      );

      expect(graph.expandableFor('01A').children, 12);
      expect(graph.expandableFor('01A').parents, 2);
      expect(graph.expandableFor('01B').parents, 0);
      expect(graph.expandableFor('01A').any, isTrue);
    });

    test('a person with nothing hidden reports nothing expandable', () {
      final graph = TreeGraph.fromResponse(data(), const {});

      expect(graph.expandableFor('01A').any, isFalse);
    });

    test('reads the truncation flag', () {
      // A tree that quietly stops is indistinguishable from a family that ends.
      final graph = TreeGraph.fromResponse(
        data(),
        const {'truncated': true, 'node_count': 800, 'ancestors_depth': 8, 'descendants_depth': 8},
      );

      expect(graph.truncated, isTrue);
      expect(graph.nodeCount, 800);
      expect(graph.ancestorsDepth, 8);
    });

    test('a response with nothing in it is empty rather than broken', () {
      expect(TreeGraph.fromResponse(data(), const {}).isEmpty, isTrue);
    });

    test('a masked person still occupies a position', () {
      final graph = TreeGraph.fromResponse(
        data(people: [
          {
            'ulid': '01P',
            'display_name': 'Private',
            'gender': 'unknown',
            'is_living': true,
            'redacted': true,
            'placeholder': true,
            'depth': 1,
          }
        ]),
        const {},
      );

      final person = graph.person('01P')!;

      expect(person.placeholder, isTrue);
      expect(person.depth, 1, reason: 'Hiding the node would misrepresent everyone else');
    });
  });
}
