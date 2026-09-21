import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/widgets/family_tab.dart';
import 'package:my_generation/models/family_bundle.dart';

Map<String, dynamic> _person(String ulid, String name) => {
  'ulid': ulid,
  'display_name': name,
  'gender': 'female',
  'is_living': true,
  'redacted': false,
};

/// A wife who married in: no parents of her own on record.
Map<String, dynamic> _wife({
  Map<String, dynamic>? link,
  List<Map<String, dynamic>> parents = const [],
}) => {
  'person': _person('01WIFE', 'Pau Khua Nem'),
  'parents': parents,
  'spouses': <dynamic>[],
  'children': <dynamic>[],
  'siblings': <dynamic>[],
  'unions': <dynamic>[],
  'family_link': ?link,
};

Future<({List<String> linked, List<String> changed, List<String> unlinked})>
pump(WidgetTester tester, Map<String, dynamic> json) async {
  final linked = <String>[];
  final changed = <String>[];
  final unlinked = <String>[];

  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: FamilyTab(
          bundle: FamilyBundle.fromJson(json),
          onOpenPerson: (_) {},
          onAddRelative: (_) {},
          onLinkFamily: () => linked.add('link'),
          onChangeLink: (link) => changed.add(link.label ?? ''),
          onUnlink: (link) => unlinked.add(link.label ?? ''),
          onReorderChildren: (_, _) {},
          onDeletePerson: (_) {},
          onMoveChild: (_, _) {},
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();

  return (linked: linked, changed: changed, unlinked: unlinked);
}

Future<void> tapLink(WidgetTester tester) async {
  await tester.tap(find.text('Link to another family'));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('nobody linked yet: the button asks', (tester) async {
    final calls = await pump(tester, _wife());

    await tapLink(tester);

    expect(calls.linked, ['link']);
    expect(find.text('Unlink'), findsNothing);
  });

  testWidgets('a link waiting for review cannot be sent twice', (tester) async {
    final calls = await pump(
      tester,
      _wife(
        link: {
          'state': 'pending',
          'kind': 'branch',
          'label': 'Khup Lian',
          'change_request_ulid': '01CR',
        },
      ),
    );

    await tapLink(tester);

    expect(calls.linked, isEmpty, reason: 'a second proposal was filed');
    expect(find.textContaining('Khup Lian family — waiting'), findsOneWidget);
    // Withdrawn or changed by whoever sent it, from Edits.
    expect(find.text('Unlink'), findsNothing);
  });

  testWidgets('a link in effect can be changed or taken back', (tester) async {
    final calls = await pump(
      tester,
      _wife(
        link: {
          'state': 'linked',
          'kind': 'branch',
          'label': 'Khup Lian',
          'change_request_ulid': '01CR',
        },
      ),
    );

    await tapLink(tester);
    expect(calls.linked, isEmpty);

    await tester.tap(find.text('Unlink'));
    await tester.tap(find.text('Change'));

    expect(calls.unlinked, ['Khup Lian']);
    expect(calls.changed, ['Khup Lian']);
  });

  testWidgets('parents on record are her family already', (tester) async {
    final calls = await pump(
      tester,
      _wife(parents: [_person('01MOTHER', 'Ciin Man')]),
    );

    await tapLink(tester);

    expect(calls.linked, isEmpty);
    expect(find.textContaining('through their parents'), findsOneWidget);
  });

  testWidgets('two records made one are not unlinked by a tap', (tester) async {
    await pump(
      tester,
      _wife(
        link: {
          'state': 'linked',
          'kind': 'merge',
          'change_request_ulid': '01CR',
        },
      ),
    );

    expect(find.text('Unlink'), findsNothing);
  });
}
