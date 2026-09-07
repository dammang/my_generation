import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/widgets/family_tab.dart';
import 'package:my_generation/models/family_bundle.dart';
import 'package:my_generation/models/person_summary.dart';

/// The shape /people/{ulid}/family actually returns, children carrying the
/// pivot the family names them by.
Map<String, dynamic> _person(
  String ulid,
  String name, {
  String gender = 'male',
  bool living = true,
  String? relationshipType,
}) => {
  'ulid': ulid,
  'display_name': name,
  'gender': gender,
  'is_living': living,
  'redacted': false,
  'relationship_type': ?relationshipType,
};

Map<String, dynamic> _bundle(
  List<Map<String, dynamic>> children, [
  List<Map<String, dynamic>> extraUnions = const [],
]) => {
  'person': _person('01FATHER', 'Thawng Dam'),
  'parents': <dynamic>[],
  'spouses': <dynamic>[],
  'children': children,
  'siblings': <dynamic>[],
  'unions': [
    {
      'ulid': '01UNION',
      'union_type': 'marriage',
      'status': 'married',
      'partners': [_person('01WIFE', 'Pau Khua Nem', gender: 'female')],
      'children': children,
      'children_count': children.length,
    },
    ...extraUnions,
  ],
};

Future<List<String>> pumpFamily(
  WidgetTester tester,
  List<Map<String, dynamic>> children, {
  void Function(PersonSummary person)? onDelete,
  void Function(FamilyUnion from, PersonSummary child)? onMove,
  List<Map<String, dynamic>> extraUnions = const [],
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 1400));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final sent = <String>[];

  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: FamilyTab(
          bundle: FamilyBundle.fromJson(_bundle(children, extraUnions)),
          onOpenPerson: (_) {},
          onAddRelative: (_) {},
          onLinkFamily: () {},
          onReorderChildren: (_, ulids) => sent.addAll(ulids),
          onDeletePerson: onDelete ?? (_) {},
          onMoveChild: onMove ?? (_, _) {},
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();

  return sent;
}

void main() {
  testWidgets('children are named by where they come', (tester) async {
    await pumpFamily(tester, [
      _person('01A', 'Zen Za Awi'),
      _person('01B', 'Kam Khua Ning', gender: 'female'),
      _person('01C', 'Thawng Za Cin', relationshipType: 'adopted'),
    ]);

    // Birth order is how these families actually name their children, so it
    // belongs on the row rather than being left for somebody to count.
    expect(find.text('1st son'), findsOneWidget);
    expect(find.text('2nd daughter'), findsOneWidget);

    // How somebody joined the family is a fact about their place in it, and a
    // family that records an adoption means it to be visible.
    expect(find.text('3rd son · adopted'), findsOneWidget);
  });

  testWidgets('moving a child sends the whole new order', (tester) async {
    final sent = await pumpFamily(tester, [
      _person('01A', 'Zen'),
      _person('01B', 'Kam'),
      _person('01C', 'Thawng'),
    ]);

    // The third child's menu.
    await tester.tap(find.byIcon(Icons.more_vert).at(2));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Move up'));
    await tester.pumpAndSettle();

    // The sequence, not the move: two siblings sharing a place is what a
    // client sending one write at a time would eventually produce.
    expect(sent, ['01A', '01C', '01B']);
  });

  testWidgets('the first child cannot be moved up', (tester) async {
    await pumpFamily(tester, [_person('01A', 'Zen'), _person('01B', 'Kam')]);

    await tester.tap(find.byIcon(Icons.more_vert).first);
    await tester.pumpAndSettle();

    final up = tester.widget<PopupMenuItem<String>>(
      find.widgetWithText(PopupMenuItem<String>, 'Move up'),
    );

    expect(up.enabled, isFalse);
  });

  testWidgets('removing somebody asks first, by name', (tester) async {
    PersonSummary? asked;

    await pumpFamily(tester, [
      _person('01A', 'Zen Za Awi'),
    ], onDelete: (person) => asked = person);

    await tester.tap(find.byIcon(Icons.more_vert).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Remove from the archive'));
    await tester.pumpAndSettle();

    // The tab hands the person up rather than deleting anything itself: the
    // confirmation belongs with the screen that owns the record.
    expect(asked?.displayName, 'Zen Za Awi');
  });

  testWidgets('a child can be moved to the other marriage', (tester) async {
    FamilyUnion? from;
    PersonSummary? moved;

    await pumpFamily(
      tester,
      [_person('01A', 'Zen Za Awi')],
      onMove: (union, child) {
        from = union;
        moved = child;
      },
      extraUnions: [
        {
          'ulid': '01UNION2',
          'union_type': 'marriage',
          'status': 'married',
          'partners': [_person('01WIFE2', 'Sing Za Cing', gender: 'female')],
          'children': <dynamic>[],
          'children_count': 0,
        },
      ],
    );

    await tester.tap(find.byIcon(Icons.more_vert).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Move to another marriage'));
    await tester.pumpAndSettle();

    // The tab hands both up: which marriage they are leaving, and who. The
    // screen that owns the record asks which marriage they are going to.
    expect(from?.ulid, '01UNION');
    expect(moved?.displayName, 'Zen Za Awi');
  });

  testWidgets('with one marriage there is nowhere to move a child', (
    tester,
  ) async {
    await pumpFamily(tester, [_person('01A', 'Zen Za Awi')]);

    await tester.tap(find.byIcon(Icons.more_vert).first);
    await tester.pumpAndSettle();

    // An option that leads nowhere is worse than no option.
    expect(find.text('Move to another marriage'), findsNothing);
  });

  group('the date line', () {
    test('says a death is known even when its year is not', () {
      final person = PersonSummary.fromJson(
        _person('01A', 'Thawng Dam', living: false),
      );

      // "No dates recorded" would report a fact the family does record — that
      // the person has died — as an absence.
      expect(person.dateLine, 'Died · year unknown');
    });

    test('still distinguishes withheld from unrecorded', () {
      final redacted = PersonSummary.fromJson({
        ..._person('01A', 'Someone'),
        'redacted': true,
      });

      expect(redacted.dateLine, 'Dates not shown');
      expect(
        PersonSummary.fromJson(_person('01B', 'Someone')).dateLine,
        'No dates recorded',
      );
    });
  });
}
