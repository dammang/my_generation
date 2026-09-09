import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/onboarding/join_clan_screen.dart';
import 'package:my_generation/features/onboarding/join_tribe_screen.dart';
import 'package:my_generation/models/clan_summary.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

const _clanUlid = '01JKJKJKJKJKJKJKJKJKJKJKJK';

Map<String, dynamic> _clan({String name = 'JK'}) => {
  'ulid': _clanUlid,
  'name': name,
  'level_label': 'Clan',
  'counts': {'people': 327},
  'tribe': {'ulid': '01TRIBETRIBETRIBETRIBETRIB', 'name': 'ZOMI'},
};

Future<FakeAdapter> _pump(
  WidgetTester tester, {
  List<Map<String, dynamic>>? clans,
  List<Map<String, dynamic>> memberships = const [],
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 1400));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'GET /api/v1/clans': [
      FakeReply(200, {
        'success': true,
        'data': clans ?? [_clan()],
      }),
    ],
    'GET /api/v1/memberships': [
      FakeReply(200, {'success': true, 'data': memberships}),
    ],
    'POST /api/v1/memberships': [
      FakeReply(201, {
        'success': true,
        'data': {'ulid': '01MEMBERSHIPMEMBERSHIPMEMB', 'status': 'pending'},
      }),
    ],
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: const MaterialApp(home: JoinClanScreen()),
    ),
  );

  await tester.pumpAndSettle();

  return adapter;
}

/// The questions a clan asks, answered.
Future<void> _answer(WidgetTester tester) async {
  for (final (label, answer) in [
    ('Your name', 'Cing Za Man'),
    ('Father', 'Thawng Dam'),
    ('Mother', 'Niang Za Dim'),
    ('Country', 'MM'),
    ('Contact', 'cing@example.com'),
  ]) {
    await tester.enterText(find.widgetWithText(TextFormField, label), answer);
  }
}

void main() {
  testWidgets('a clan can be asked to join', (tester) async {
    final adapter = await _pump(tester);

    expect(find.text('JK'), findsOneWidget);
    expect(find.textContaining('ZOMI'), findsOneWidget);

    await tester.tap(find.text('Ask to join'));
    await tester.pumpAndSettle();

    // Nothing sent yet: a clan asks who your parents were first.
    expect(
      adapter.received.where((r) => r.method == 'POST'),
      isEmpty,
      reason: 'the request went before the questions were answered',
    );

    expect(find.text('Join JK'), findsOneWidget);

    await _answer(tester);
    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();

    final asked = adapter.received.where(
      (r) => r.method == 'POST' && r.path == '/api/v1/memberships',
    );

    expect(asked, hasLength(1));

    // The clan, not the tribe: the endpoint takes either, and sending the
    // wrong one would grant nothing while appearing to succeed.
    expect(asked.single.data, {
      'scope_type': 'clan',
      'scope_ulid': _clanUlid,
      'applicant_name': 'Cing Za Man',
      'father_name': 'Thawng Dam',
      'mother_name': 'Niang Za Dim',
      'country': 'MM',
      'contact': 'cing@example.com',
    });

    expect(find.textContaining('Asked to join JK'), findsOneWidget);
  });

  testWidgets('the questions must be answered before it is sent', (
    tester,
  ) async {
    final adapter = await _pump(tester);

    await tester.tap(find.text('Ask to join'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();

    expect(find.text('Please give Your name.'), findsOneWidget);
    expect(adapter.received.where((r) => r.method == 'POST'), isEmpty);
  });

  testWidgets('grandparents may be left unknown', (tester) async {
    // The ordinary state of an oral archive, not an evasion.
    final adapter = await _pump(tester);

    await tester.tap(find.text('Ask to join'));
    await tester.pumpAndSettle();
    await _answer(tester);
    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();

    final sent =
        adapter.received
                .singleWhere(
                  (r) => r.method == 'POST' && r.path == '/api/v1/memberships',
                )
                .data
            as Map;

    expect(sent.containsKey('grandfather_name'), isFalse);
    expect(sent.containsKey('grandmother_name'), isFalse);
  });

  testWidgets('a clan already asked for is not offered again', (tester) async {
    await _pump(
      tester,
      memberships: [
        {
          'ulid': '01MEMBERSHIPMEMBERSHIPMEMB',
          'status': 'pending',
          'scope': {'type': 'clan', 'ulid': _clanUlid, 'name': 'JK'},
        },
      ],
    );

    expect(find.text('Requested'), findsOneWidget);
    expect(find.text('Ask to join'), findsNothing);
  });

  testWidgets('an empty list says who to ask', (tester) async {
    await _pump(tester, clans: []);

    // Never "try again": the answer is a person, not a retry.
    expect(
      find.textContaining('No clans have been created yet'),
      findsOneWidget,
    );
    expect(find.textContaining('Ask whoever set up'), findsOneWidget);
  });

  testWidgets('a tribe is still asked for as a tribe', (tester) async {
    // Both screens are the same screen underneath. Sending the wrong scope
    // type would grant nothing while appearing to succeed, and it is the one
    // thing sharing an implementation could quietly break.
    await tester.binding.setSurfaceSize(const Size(402, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final adapter = FakeAdapter({
      'GET /api/v1/tribes': [
        FakeReply(200, {
          'success': true,
          'data': [
            {
              'ulid': '01TRIBETRIBETRIBETRIBETRIB',
              'name': 'ZOMI',
              'counts': {'people': 327, 'clans': 1},
            },
          ],
        }),
      ],
      'GET /api/v1/memberships': [
        FakeReply(200, {'success': true, 'data': <dynamic>[]}),
      ],
      'POST /api/v1/memberships': [
        FakeReply(201, {
          'success': true,
          'data': {'ulid': '01MEMBERSHIPMEMBERSHIPMEMB', 'status': 'pending'},
        }),
      ],
    });

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
          secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        ],
        child: const MaterialApp(home: JoinTribeScreen()),
      ),
    );

    await tester.pumpAndSettle();
    await tester.tap(find.text('Ask to join'));
    await tester.pumpAndSettle();

    final asked = adapter.received.singleWhere(
      (r) => r.method == 'POST' && r.path == '/api/v1/memberships',
    );

    expect(asked.data, {
      'scope_type': 'tribe',
      'scope_ulid': '01TRIBETRIBETRIBETRIBETRIB',
    });
  });

  test('a clan says which tribe and how large it is', () {
    final clan = ClanSummary.fromJson(_clan());

    expect(clan.subtitle, 'ZOMI · Clan · 327 people');
  });
}
