import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/widgets/tree_person_card.dart';
import 'package:my_generation/models/person_summary.dart';
import 'package:my_generation/models/tree_graph.dart';

PersonSummary _person(String ulid, {bool hasParents = false}) =>
    PersonSummary.fromJson({
      'ulid': ulid,
      'display_name': ulid,
      'gender': 'female',
      'is_living': false,
      'redacted': false,
      'has_parents': hasParents,
    });

TreeGraph _graph({
  required bool wifeHasParents,
  bool wifeParentOnChart = false,
}) => TreeGraph(
  focusUlid: 'husband',
  people: {
    'husband': _person('husband', hasParents: true),
    'wife': _person('wife', hasParents: wifeHasParents),
    if (wifeParentOnChart) 'wifeFather': _person('wifeFather'),
  },
  unions: const [
    TreeUnion(ulid: 'u1', partnerUlids: ['husband', 'wife'], childUlids: []),
  ],
  edges: [
    const TreeEdge(parentUlid: 'grandfather', childUlid: 'husband'),
    if (wifeParentOnChart)
      const TreeEdge(parentUlid: 'wifeFather', childUlid: 'wife'),
  ],
  expandable: const {},
);

void main() {
  group('a family that is not on this chart', () {
    test('a wife with her own parents recorded is marked', () {
      expect(_graph(wifeHasParents: true).linkedElsewhere('wife'), isTrue);
    });

    test('a wife with no family of her own is not', () {
      // Before anybody claims her record she is a name beside her husband,
      // and the card must not promise a family that is not there.
      expect(_graph(wifeHasParents: false).linkedElsewhere('wife'), isFalse);
    });

    test('somebody whose parents are on this chart is not', () {
      // The husband has parents too. The mark means "leads somewhere the lines
      // do not", not "has parents" — otherwise it would be on everybody.
      expect(
        _graph(
          wifeHasParents: true,
          wifeParentOnChart: true,
        ).linkedElsewhere('wife'),
        isFalse,
      );
      expect(_graph(wifeHasParents: true).linkedElsewhere('husband'), isFalse);
    });
  });

  testWidgets('the card says so, in the picture and to a screen reader', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Center(
            child: SizedBox(
              width: 160,
              height: 120,
              child: TreePersonCard(
                person: _person('Cing Man', hasParents: true),
                isFocus: false,
                expandable: const Expandable(parents: 0, children: 0),
                linkedElsewhere: true,
              ),
            ),
          ),
        ),
      ),
    );

    expect(find.byIcon(Icons.account_tree_outlined), findsOneWidget);

    final semantics = tester.getSemantics(find.byType(TreePersonCard));

    expect(
      semantics.label,
      contains('has a family of their own on another chart'),
    );
  });
}
