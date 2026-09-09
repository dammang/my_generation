import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/clans/view/membership_requests_screen.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

const _clan = '01JKJKJKJKJKJKJKJKJKJKJKJK';
const _request = '01REQUESTREQUESTREQUESTREQ';

Map<String, dynamic> _pending({String name = 'Cing Za Man'}) => {
  'ulid': _request,
  'status': 'pending',
  'created_at': '2026-09-06T10:00:00+00:00',
  'scope': {'type': 'clan', 'ulid': _clan, 'name': 'JK'},
  'user': {'ulid': '01USERUSERUSERUSERUSERUSER', 'name': name},
  'applicant': {
    'name': name,
    'father': 'Thawng Dam',
    'mother': 'Niang Za Dim',
    'country': 'MM',
    'contact': 'cing@example.com',
  },
};

Future<FakeAdapter> _pump(
  WidgetTester tester, {
  List<Map<String, dynamic>>? queue,
  List<Map<String, dynamic>>? scopes,
  FakeReply? decision,
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 1200));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'GET /api/v1/scope-roles/administered': [
      FakeReply(200, {
        'success': true,
        'data':
            scopes ??
            [
              {
                'scope_type': 'clan',
                'scope_ulid': _clan,
                'name': 'JK',
                'assignable_roles': <String>[],
              },
            ],
      }),
    ],
    'GET /api/v1/scope-members': [
      FakeReply(200, {
        'success': true,
        'data': queue ?? [_pending()],
      }),
    ],
    'POST /api/v1/memberships/$_request/approve': [
      decision ?? FakeReply(200, {'success': true, 'data': _pending()}),
    ],
    'POST /api/v1/memberships/$_request/reject': [
      FakeReply(200, {'success': true, 'data': _pending()}),
    ],
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: const MaterialApp(home: MembershipRequestsScreen()),
    ),
  );

  await tester.pumpAndSettle();

  return adapter;
}

void main() {
  testWidgets('somebody waiting is shown with who and where', (tester) async {
    await _pump(tester);

    expect(find.text('Cing Za Man'), findsOneWidget);
    expect(find.text('Asked to join JK'), findsOneWidget);
    expect(find.textContaining('Asked '), findsWidgets);
    expect(find.text('Approve'), findsOneWidget);
    expect(find.text('Decline'), findsOneWidget);
  });

  testWidgets('the reviewer reads what the applicant said', (tester) async {
    // The whole reason for asking: deciding whether a stranger belongs to a
    // family on nothing but an account name is not a decision.
    await _pump(tester);

    expect(find.text('Father'), findsOneWidget);
    expect(find.text('Thawng Dam'), findsOneWidget);
    expect(find.text('Mother'), findsOneWidget);
    expect(find.text('Niang Za Dim'), findsOneWidget);
    expect(find.text('MM'), findsOneWidget);
    expect(find.text('cing@example.com'), findsOneWidget);

    // Not asked for, not shown — rather than an empty row implying it was
    // refused.
    expect(find.text('Grandfather'), findsNothing);
  });

  testWidgets('approving sends the approval', (tester) async {
    final adapter = await _pump(tester);

    await tester.tap(find.text('Approve'));
    await tester.pumpAndSettle();

    expect(
      adapter.received.map((r) => '${r.method} ${r.path}'),
      contains('POST /api/v1/memberships/$_request/approve'),
    );
    expect(find.textContaining('Cing Za Man is in JK'), findsOneWidget);

    // The queue is re-read after a decision, so an answered request leaves it.
    expect(
      adapter.received.where((r) => r.path == '/api/v1/scope-members').length,
      greaterThan(1),
    );
  });

  testWidgets('declining sends the rejection, not the approval', (
    tester,
  ) async {
    final adapter = await _pump(tester);

    await tester.tap(find.text('Decline'));
    await tester.pumpAndSettle();

    final sent = adapter.received
        .where((r) => r.method == 'POST')
        .map((r) => r.path)
        .toList();

    expect(sent, contains('/api/v1/memberships/$_request/reject'));
    expect(sent, isNot(contains('/api/v1/memberships/$_request/approve')));
  });

  testWidgets('a refusal is said, and the request stays', (tester) async {
    final adapter = await _pump(
      tester,
      decision: FakeReply(403, {
        'success': false,
        'message': 'You do not administer this scope.',
      }),
    );

    final before = adapter.received
        .where((r) => r.path == '/api/v1/scope-members')
        .length;

    await tester.tap(find.text('Approve'));
    await tester.pumpAndSettle();

    expect(find.textContaining('You do not administer'), findsOneWidget);
    expect(find.text('Cing Za Man'), findsOneWidget);

    // Discriminating: on success the queue is refetched, so "the row is still
    // there" proves nothing on its own — the list would simply have been
    // reloaded. A refusal must not even ask, or a request that was never
    // answered could quietly leave the queue.
    expect(
      adapter.received.where((r) => r.path == '/api/v1/scope-members').length,
      before,
    );
  });

  testWidgets('an empty queue says so plainly', (tester) async {
    await _pump(tester, queue: []);

    expect(find.text('Nobody is waiting.'), findsOneWidget);
  });
}
