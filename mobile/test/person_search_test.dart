import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/search/view/person_search_screen.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

Map<String, dynamic> _person(String ulid, String name) => {
  'ulid': ulid,
  'display_name': name,
  'gender': 'male',
  'is_living': true,
  'redacted': false,
};

Map<String, dynamic> _envelope(List<Map<String, dynamic>> people) => {
  'success': true,
  'data': people,
  'warnings': const <dynamic>[],
};

Future<FakeAdapter> pumpSearch(
  WidgetTester tester, {
  List<Map<String, dynamic>> results = const [],
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 874));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'GET /api/v1/people': [FakeReply(200, _envelope(results))],
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: const MaterialApp(home: PersonSearchScreen()),
    ),
  );
  await tester.pumpAndSettle();

  return adapter;
}

/// The debounce is 400ms; anything shorter and the assertion races it.
Future<void> type(WidgetTester tester, String text) async {
  await tester.enterText(find.byType(TextField), text);
  await tester.pump(const Duration(milliseconds: 500));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('opens asking for a name rather than showing an empty list', (
    tester,
  ) async {
    await pumpSearch(tester);

    expect(find.text('Search the archive'), findsOneWidget);
    expect(find.text('Nobody by that name'), findsNothing);
  });

  testWidgets('a single letter never reaches the server', (tester) async {
    final adapter = await pumpSearch(tester);

    await type(tester, 'J');

    // Two characters is the server's own floor; sending one letter would ask
    // it to return most of the archive.
    expect(adapter.received.where((r) => r.path == '/api/v1/people'), isEmpty);
  });

  testWidgets('a name finds people and sends it as q', (tester) async {
    final adapter = await pumpSearch(
      tester,
      results: [_person('01AAA', 'James Cole'), _person('01BBB', 'Jane Cole')],
    );

    await type(tester, 'cole');

    expect(find.text('James Cole'), findsOneWidget);
    expect(find.text('Jane Cole'), findsOneWidget);

    final sent = adapter.received.firstWhere((r) => r.path == '/api/v1/people');
    expect(sent.queryParameters['q'], 'cole');
  });

  testWidgets('no match explains the prefix rule instead of just saying none', (
    tester,
  ) async {
    await pumpSearch(tester);

    await type(tester, 'ole');

    expect(find.text('Nobody by that name'), findsOneWidget);
    // Without this, somebody searching the middle of a name concludes the
    // record is missing rather than that they searched the wrong end of it.
    expect(find.textContaining('matched from the beginning'), findsOneWidget);
  });

  testWidgets('clearing the field returns to the prompt', (tester) async {
    await pumpSearch(tester, results: [_person('01AAA', 'James Cole')]);

    await type(tester, 'cole');
    expect(find.text('James Cole'), findsOneWidget);

    await type(tester, '');

    expect(find.text('James Cole'), findsNothing);
    expect(find.text('Search the archive'), findsOneWidget);
  });
}
