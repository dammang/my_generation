import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/core/theme/app_theme.dart';
import 'package:my_generation/features/review/view/review_queue_screen.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

Map<String, dynamic> _queue({required bool canReview, List<dynamic> data = const []}) => {
      'success': true,
      'data': data,
      'meta': {'can_review': canReview, 'filter': 'review'},
      'warnings': const <dynamic>[],
    };

final _pending = {
  'ulid': '01CHANGE',
  'status': 'pending',
  'operation': 'update',
  'reason': 'The baptismal register spells it Ngun, not Ngul.',
  'diff': [
    {'field': 'first_name', 'label': 'First name', 'before': 'Ngul', 'after': 'Ngun'},
  ],
  'target': {'ulid': '01PERSON', 'label': 'Ngul Muan'},
  'requested_by': {'ulid': '01USER', 'name': 'Cin Hlei'},
  'submitted_at': '2026-09-03T06:00:00+00:00',
};

Future<void> pumpQueue(WidgetTester tester, FakeAdapter adapter, {int tab = 0}) async {
  // A phone, not the 800x600 default. Card layout that survives a wide test
  // viewport can still fail on the width people actually hold.
  await tester.binding.setSurfaceSize(const Size(402, 874));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(fakeApiClient(adapter))],
      child: MaterialApp(home: ReviewQueueScreen(initialTab: tab)),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('renders a pending proposal with its diff', (tester) async {
    final adapter = FakeAdapter({
      'GET /api/v1/change-requests': [
        FakeReply(200, _queue(canReview: true, data: [_pending])),
      ],
    });

    await pumpQueue(tester, adapter, tab: 1);

    expect(tester.takeException(), isNull);
    expect(find.text('Ngul Muan'), findsWidgets);
    expect(find.text('Ngul'), findsWidgets);
    expect(find.text('Ngun'), findsWidgets);
    expect(find.text('Accept'), findsOneWidget);
    expect(find.text('Decline'), findsOneWidget);
  });

  testWidgets('opens on the tab the caller asked for', (tester) async {
    // The queue is reached from a notification that already knows which side
    // matters. Landing on the wrong tab makes the reviewer hunt for the work.
    final adapter = FakeAdapter({
      'GET /api/v1/change-requests': [
        FakeReply(200, _queue(canReview: true, data: [_pending])),
      ],
    });

    await pumpQueue(tester, adapter, tab: 1);

    final controller = DefaultTabController.of(
      tester.element(find.byType(TabBarView)),
    );

    expect(controller.index, 1);
  });

  _themeTests();

  testWidgets('shows no review tab when the account reviews nothing',
      (tester) async {
    final adapter = FakeAdapter({
      'GET /api/v1/change-requests': [FakeReply(200, _queue(canReview: false))],
    });

    await pumpQueue(tester, adapter);

    // Offering a queue somebody cannot act on is worse than not offering it.
    expect(find.text('To review'), findsNothing);
    expect(find.text('You have not suggested anything yet'), findsOneWidget);
  });
}

/// A button that demands infinite width cannot sit beside anything.
///
/// The theme used Size.fromHeight, whose width is double.infinity. Forms hid
/// it — a list stretches its children anyway — so it only surfaced when a
/// button was finally placed in a row.
void _themeTests() {
  testWidgets('a filled button can sit in a row', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: Scaffold(
          body: Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton(onPressed: () {}, child: const Text('Decline')),
              FilledButton(onPressed: () {}, child: const Text('Accept')),
            ],
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);

    // Sized to its label, not to the screen.
    expect(tester.getSize(find.byType(FilledButton)).width, lessThan(300));
  });

  testWidgets('a filled button still fills the width of a form', (tester) async {
    await tester.binding.setSurfaceSize(const Size(402, 874));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light(),
        home: Scaffold(
          body: ListView(
            children: [FilledButton(onPressed: () {}, child: const Text('Sign in'))],
          ),
        ),
      ),
    );

    expect(tester.getSize(find.byType(FilledButton)).width, 402);
    expect(tester.getSize(find.byType(FilledButton)).height, greaterThanOrEqualTo(52));
  });
}
