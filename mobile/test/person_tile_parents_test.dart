import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/models/person_summary.dart';
import 'package:my_generation/widgets/person_tile.dart';

PersonSummary _person({
  String? father,
  String? mother,
  String? birth,
  String? death,
  bool living = true,
}) => PersonSummary.fromJson({
  'ulid': '01THAWNGTHAWNGTHAWNGTHAWNG',
  'display_name': 'THAWNG DAM',
  'gender': 'male',
  'is_living': living,
  'redacted': false,
  if (birth != null) 'birth': {'display': birth},
  if (death != null) 'death': {'display': death},
  'parents': {'father': ?father, 'mother': ?mother},
});

Future<void> _pump(WidgetTester tester, PersonSummary person) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(body: PersonTile(person: person)),
    ),
  );
  await tester.pump();
}

void main() {
  testWidgets('with no dates, the parents identify them', (tester) async {
    // A column of names all reading "No dates recorded" cannot be told apart,
    // and a family may hold a dozen people called Thawng.
    await _pump(tester, _person(father: 'HAU NENG', mother: 'DIM ZEL'));

    expect(find.text('Father: HAU NENG'), findsOneWidget);
    expect(find.text('Mother: DIM ZEL'), findsOneWidget);
    expect(find.text('No dates recorded'), findsNothing);
  });

  testWidgets('the father is above the mother', (tester) async {
    await _pump(tester, _person(father: 'HAU NENG', mother: 'DIM ZEL'));

    final father = tester.getTopLeft(find.text('Father: HAU NENG'));
    final mother = tester.getTopLeft(find.text('Mother: DIM ZEL'));

    expect(father.dy, lessThan(mother.dy));
  });

  testWidgets('one parent alone is still worth showing', (tester) async {
    await _pump(tester, _person(mother: 'DIM ZEL'));

    expect(find.text('Mother: DIM ZEL'), findsOneWidget);
    expect(find.textContaining('Father'), findsNothing);
  });

  testWidgets('dates win where the archive has them', (tester) async {
    // Shorter, and they place somebody in time; the parents are the fallback,
    // not an addition.
    await _pump(
      tester,
      _person(father: 'HAU NENG', birth: '1920', death: '1998', living: false),
    );

    expect(find.text('1920–1998'), findsOneWidget);
    expect(find.textContaining('Father'), findsNothing);
  });

  testWidgets('with neither, it says so rather than showing nothing', (
    tester,
  ) async {
    await _pump(tester, _person());

    expect(find.text('No dates recorded'), findsOneWidget);
  });
}
