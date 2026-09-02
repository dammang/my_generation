import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/view/add_relative_screen.dart';
import 'package:my_generation/providers/app_providers.dart';
import 'package:my_generation/repositories/person_repository.dart';

import 'support/fake_api.dart';

const _anchor = '01ANCHOR';
const _addRelative = 'POST /api/v1/people/$_anchor/relatives';

Map<String, dynamic> _createdPerson(String name) => {
  'success': true,
  'data': {
    'person': {
      'ulid': '01NEW',
      'display_name': name,
      'gender': 'male',
      'is_living': false,
      'redacted': false,
    },
    'created': true,
    'change_request': null,
  },
  'meta': const <String, dynamic>{},
  'warnings': const <dynamic>[],
};

/// The server refusing to guess which marriage a child belongs to, with the
/// options attached as data.
Map<String, dynamic> get _ambiguous => {
  'success': false,
  'message':
      'This person has more than one union. Say which one the child belongs to.',
  'code': 'UNION_AMBIGUOUS',
  'errors': {
    'union_ulid': ['Marriage to Ngun Hlei', 'Marriage to Par Tial'],
  },
  'meta': {
    'choices': [
      {'ulid': '01UNIONA', 'label': 'Marriage to Ngun Hlei'},
      {'ulid': '01UNIONB', 'label': 'Marriage to Par Tial'},
    ],
  },
};

Future<void> pumpScreen(WidgetTester tester, FakeAdapter adapter) async {
  // The form is taller than the default 800x600 test viewport, and a ListView
  // never builds what is off-screen — so a finder would match nothing at all,
  // failing in a way that says nothing about the form.
  await tester.binding.setSurfaceSize(const Size(1000, 2400));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(fakeApiClient(adapter))],
      child: const MaterialApp(
        home: AddRelativeScreen(
          anchorUlid: _anchor,
          anchorName: 'Thawng Dam',
          initialRelation: 'child',
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> tapScrolled(WidgetTester tester, Finder finder) async {
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
  await tester.tap(finder);
  await tester.pumpAndSettle();
}

Future<void> fillNameAndSubmit(WidgetTester tester, String name) async {
  await tester.enterText(find.byType(TextFormField).first, name);
  await tapScrolled(tester, find.text('Add to the family'));
}

void main() {
  testWidgets('adds a relative and returns the result', (tester) async {
    final adapter = FakeAdapter({
      _addRelative: [FakeReply(201, _createdPerson('Bawi Thawng'))],
    });

    await pumpScreen(tester, adapter);
    await fillNameAndSubmit(tester, 'Bawi');

    expect(adapter.received.length, 1);
    expect(adapter.received.single.data['relation'], 'child');
    expect(adapter.received.single.data['person']['first_name'], 'Bawi');
  });

  testWidgets('asks which marriage when the server refuses to guess', (
    tester,
  ) async {
    final adapter = FakeAdapter({
      _addRelative: [
        FakeReply(422, _ambiguous),
        FakeReply(201, _createdPerson('Bawi Thawng')),
      ],
    });

    await pumpScreen(tester, adapter);
    await fillNameAndSubmit(tester, 'Bawi');

    // The refusal becomes a question, built from the choices the server sent
    // as data. Parsing ids back out of the human message would be the bug.
    expect(find.text('Which marriage?'), findsOneWidget);
    expect(find.text('Marriage to Ngun Hlei'), findsOneWidget);
    expect(find.text('Marriage to Par Tial'), findsOneWidget);

    await tapScrolled(tester, find.text('Marriage to Par Tial'));
    await tapScrolled(tester, find.text('Add to the family'));

    expect(adapter.received.length, 2);
    // The retry carries the choice; nothing else about the form was lost.
    expect(adapter.received.last.data['union_ulid'], '01UNIONB');
    expect(adapter.received.last.data['person']['first_name'], 'Bawi');
  });

  testWidgets('drops a stale marriage choice when the relation changes', (
    tester,
  ) async {
    final adapter = FakeAdapter({
      _addRelative: [
        FakeReply(422, _ambiguous),
        FakeReply(201, _createdPerson('Ngun Hlei')),
      ],
    });

    await pumpScreen(tester, adapter);
    await fillNameAndSubmit(tester, 'Ngun');

    expect(find.text('Which marriage?'), findsOneWidget);

    // Switching to "spouse" makes the marriage question meaningless. Carrying
    // the old answer forward would attach the write to an unrelated union.
    await tapScrolled(tester, find.text('Spouse'));

    expect(find.text('Which marriage?'), findsNothing);

    await tapScrolled(tester, find.text('Add to the family'));

    expect(adapter.received.last.data.containsKey('union_ulid'), isFalse);
    expect(adapter.received.last.data['relation'], 'spouse');
  });

  testWidgets('shows a plain failure without offering a marriage choice', (
    tester,
  ) async {
    final adapter = FakeAdapter({
      _addRelative: [
        FakeReply(422, {
          'success': false,
          'message': 'A person cannot be their own parent.',
          'code': 'GENEALOGY_RULE_VIOLATED',
          'errors': const <String, dynamic>{},
        }),
      ],
    });

    await pumpScreen(tester, adapter);
    await fillNameAndSubmit(tester, 'Bawi');

    expect(find.text('A person cannot be their own parent.'), findsOneWidget);
    expect(find.text('Which marriage?'), findsNothing);
  });

  test('carries warnings that arrived with a successful write', () async {
    // A 200 with doubt attached. Genealogy writes routinely succeed and carry
    // a warning, and a client that reads only `data` silently discards the
    // server's one chance to say something looks wrong.
    final adapter = FakeAdapter({
      _addRelative: [
        FakeReply(200, {
          ..._createdPerson('Bawi Thawng'),
          'warnings': [
            {
              'code': 'CHILD_BORN_AFTER_PARENT_DEATH',
              'message':
                  'Born 20 years after the father\u2019s recorded death.',
              'field': 'birth',
            },
          ],
        }),
      ],
    });

    final result = await PersonRepository(fakeApiClient(adapter)).addRelative(
      anchorUlid: _anchor,
      relation: 'child',
      person: const {'first_name': 'Bawi'},
    );

    expect(result.created, isTrue);
    expect(result.warnings.single.code, 'CHILD_BORN_AFTER_PARENT_DEATH');
  });

  test('reports a person the server matched rather than created', () async {
    // `created: false` means the server recognised an existing record. Telling
    // the contributor they added somebody who was already there would be wrong.
    final adapter = FakeAdapter({
      _addRelative: [
        FakeReply(200, {
          ..._createdPerson('Bawi Thawng'),
          'data': {
            ..._createdPerson('Bawi Thawng')['data'] as Map<String, dynamic>,
            'created': false,
          },
        }),
      ],
    });

    final result = await PersonRepository(fakeApiClient(adapter)).addRelative(
      anchorUlid: _anchor,
      relation: 'child',
      person: const {'first_name': 'Bawi'},
    );

    expect(result.created, isFalse);
  });
}
