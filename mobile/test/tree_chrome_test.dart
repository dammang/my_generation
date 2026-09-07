import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/layout/tree_layout_engine.dart';
import 'package:my_generation/features/tree/view/tree_canvas.dart';
import 'package:my_generation/models/person_summary.dart';
import 'package:my_generation/models/tree_graph.dart';

PersonSummary _person(String ulid, int depth) => PersonSummary(
  ulid: ulid,
  displayName: ulid,
  gender: 'unknown',
  isLiving: false,
  redacted: false,
  depth: depth,
);

/// A father and two children, laid out for real.
TreeGraph _graph() => TreeGraph(
  focusUlid: 'father',
  people: {
    'father': _person('father', 0),
    'first': _person('first', 1),
    'second': _person('second', 1),
  },
  unions: const [
    TreeUnion(
      ulid: 'u1',
      partnerUlids: ['father'],
      childUlids: ['first', 'second'],
    ),
  ],
  edges: const [
    TreeEdge(parentUlid: 'father', childUlid: 'first'),
    TreeEdge(parentUlid: 'father', childUlid: 'second'),
  ],
  expandable: const {},
);

Future<List<bool>> pumpCanvas(WidgetTester tester) async {
  final graph = _graph();
  final calls = <bool>[];

  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: TreeCanvas(
          graph: graph,
          layout: const TreeLayoutEngine().layout(graph),
          controller: TransformationController(),
          onPersonTap: (_) {},
          onPersonLongPress: (_) {},
          onExpand: (_, _) {},
          onScrolled: calls.add,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();

  return calls;
}

void main() {
  testWidgets('pulling the chart down the family asks for the room', (
    tester,
  ) async {
    final calls = await pumpCanvas(tester);

    // The finger going up pulls the chart further down a family. That is
    // "scrolling down", and it is when the screen furniture is in the way.
    await tester.drag(find.byType(TreeCanvas), const Offset(0, -120));
    await tester.pumpAndSettle();

    expect(calls, contains(true));
  });

  testWidgets('pulling it back up brings everything back', (tester) async {
    final calls = await pumpCanvas(tester);

    await tester.drag(find.byType(TreeCanvas), const Offset(0, 120));
    await tester.pumpAndSettle();

    expect(calls, contains(false));
  });

  testWidgets('a nudge is not a decision', (tester) async {
    final calls = await pumpCanvas(tester);

    // A single pointer wobbles. Chrome that answered every frame would
    // flicker for the whole gesture.
    await tester.drag(find.byType(TreeCanvas), const Offset(0, -6));
    await tester.pumpAndSettle();

    expect(calls, isEmpty);
  });

  testWidgets('sideways panning leaves it alone', (tester) async {
    final calls = await pumpCanvas(tester);

    await tester.drag(find.byType(TreeCanvas), const Offset(-200, 0));
    await tester.pumpAndSettle();

    expect(calls, isEmpty);
  });
}
