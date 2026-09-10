import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/widgets/person_notes.dart';
import 'package:my_generation/models/note.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

const _ulid = '01THAWNGTHAWNGTHAWNGTHAWNG';

Map<String, dynamic> _note({
  String audience = 'descendants',
  String label = 'Descendants',
  bool mine = false,
}) => {
  'ulid': '01NOTENOTENOTENOTENOTENOTE',
  'body': 'He is the one they called Pau.',
  'audience': audience,
  'audience_label': label,
  'mine': mine,
  'written_at': '2026-09-10T10:00:00+00:00',
  'author': {'ulid': '01USERUSERUSERUSERUSERUSER', 'name': 'Dam Suan Mang'},
};

Future<FakeAdapter> _pump(
  WidgetTester tester, {
  List<Map<String, dynamic>>? notes,
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 1400));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'GET /api/v1/people/$_ulid/notes': [
      FakeReply(200, {
        'success': true,
        'data': notes ?? [_note()],
      }),
    ],
    'POST /api/v1/people/$_ulid/notes': [
      FakeReply(201, {'success': true, 'data': _note(mine: true)}),
    ],
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: const MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(child: PersonNotes(ulid: _ulid)),
        ),
      ),
    ),
  );

  await tester.pumpAndSettle();

  return adapter;
}

void main() {
  testWidgets('a note says who may read it, on the note', (tester) async {
    // A writer who has to remember what they chose will eventually be wrong,
    // and being wrong about that is the whole risk of the feature.
    await _pump(tester);

    expect(find.text('He is the one they called Pau.'), findsOneWidget);
    expect(find.textContaining('My descendants'), findsOneWidget);
    expect(find.textContaining('Dam Suan Mang'), findsOneWidget);
  });

  testWidgets('an audience this build does not know still says something', (
    tester,
  ) async {
    // Never a blank where the reach should be: a note whose audience cannot be
    // read is the one case where guessing "everyone" would be worst.
    await _pump(
      tester,
      notes: [_note(audience: 'some_future_scale', label: 'The committee')],
    );

    expect(find.textContaining('The committee'), findsOneWidget);
  });

  testWidgets('only your own note offers to be removed', (tester) async {
    await _pump(tester);
    expect(find.byIcon(Icons.delete_outline), findsNothing);

    await _pump(tester, notes: [_note(mine: true)]);
    expect(find.byIcon(Icons.delete_outline), findsOneWidget);
  });

  testWidgets('writing one asks who it is for and sends that', (tester) async {
    final adapter = await _pump(tester, notes: []);

    await tester.tap(find.text('Add'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'He is my grandfather.');
    await tester.tap(find.text('My descendants'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Save note'));
    await tester.pumpAndSettle();

    final sent = adapter.received.singleWhere((r) => r.method == 'POST');

    expect(sent.data, {
      'body': 'He is my grandfather.',
      'audience': 'descendants',
    });
  });

  testWidgets('an empty note is refused before it is sent', (tester) async {
    final adapter = await _pump(tester, notes: []);

    await tester.tap(find.text('Add'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save note'));
    await tester.pumpAndSettle();

    expect(find.text('Write something first.'), findsOneWidget);
    expect(adapter.received.where((r) => r.method == 'POST'), isEmpty);
  });

  test('every audience the app offers has a name and an explanation', () {
    for (final audience in NoteAudience.values) {
      expect(audience.label, isNotEmpty);
      expect(audience.describe, isNotEmpty);
    }

    expect(NoteAudience.fromWire('descendants'), NoteAudience.descendants);
    expect(NoteAudience.fromWire('nonsense'), isNull);
  });
}
