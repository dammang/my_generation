import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/clans/view/clan_registrations_screen.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

/// The shape ClanRegistrationResource actually sends.
Map<String, dynamic> _registration({
  required String ulid,
  required String name,
  String status = 'pending',
  bool canDecide = false,
  bool canWithdraw = false,
  String? requester,
}) => {
  'ulid': ulid,
  'status': status,
  'name': name,
  'native_name': null,
  'description': null,
  'statement': 'My grandfather always said we were $name.',
  'decision_note': null,
  'decided_at': null,
  'created_at': '2026-09-01T10:00:00+00:00',
  'tribe': {'ulid': '01TRIBETRIBETRIBETRIBETRIB', 'name': 'Zomi'},
  'requester': requester == null
      ? null
      : {'ulid': '01USERUSERUSERUSERUSERUSER', 'name': requester},
  'can_decide': canDecide,
  'can_withdraw': canWithdraw,
};

Future<FakeAdapter> pumpList(
  WidgetTester tester,
  List<Map<String, dynamic>> rows, {
  Map<String, List<FakeReply>> extra = const {},
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 874));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'GET /api/v1/clan-registrations': [
      FakeReply(200, {'success': true, 'data': rows}),
    ],
    'GET /api/v1/scope-roles/administered': [
      FakeReply(200, {'success': true, 'data': <dynamic>[]}),
    ],
    ...extra,
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: const MaterialApp(home: ClanRegistrationsScreen()),
    ),
  );
  await tester.pumpAndSettle();

  return adapter;
}

void main() {
  testWidgets('the server decides which buttons exist', (tester) async {
    await pumpList(tester, [
      _registration(
        ulid: '01AAAAAAAAAAAAAAAAAAAAAAAA',
        name: 'Guite',
        canDecide: true,
        requester: 'Dam Mang',
      ),
      _registration(
        ulid: '01BBBBBBBBBBBBBBBBBBBBBBBB',
        name: 'Sukte',
        canWithdraw: true,
      ),
    ]);

    // Two sections, because the same object at two stages means two different
    // things to the person reading it.
    expect(find.text('Waiting on you'), findsOneWidget);
    expect(find.text('Your requests'), findsOneWidget);

    expect(find.text('Approve'), findsOneWidget);
    expect(find.text('Refuse'), findsOneWidget);
    expect(find.text('Withdraw'), findsOneWidget);

    // Whose request it is, on the one somebody has to decide.
    expect(find.text('Asked by Dam Mang in Zomi'), findsOneWidget);
  });

  testWidgets('approving says what it will do before it does it', (
    tester,
  ) async {
    final adapter = await pumpList(
      tester,
      [
        _registration(
          ulid: '01AAAAAAAAAAAAAAAAAAAAAAAA',
          name: 'Guite',
          canDecide: true,
          requester: 'Dam Mang',
        ),
      ],
      extra: {
        'POST /api/v1/clan-registrations/01AAAAAAAAAAAAAAAAAAAAAAAA/approve': [
          FakeReply(200, {'success': true, 'data': <String, dynamic>{}}),
        ],
      },
    );

    await tester.tap(find.text('Approve'));
    await tester.pumpAndSettle();

    // The consequence, not "are you sure?" about something off screen:
    // approving creates a clan and hands it to somebody.
    expect(
      find.textContaining('makes Dam Mang its administrator'),
      findsOneWidget,
    );

    await tester.tap(
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.widgetWithText(FilledButton, 'Approve'),
      ),
    );
    await tester.pumpAndSettle();

    expect(
      adapter.received.any(
        (r) =>
            r.method == 'POST' &&
            r.path ==
                '/api/v1/clan-registrations/01AAAAAAAAAAAAAAAAAAAAAAAA/approve',
      ),
      isTrue,
    );
  });

  testWidgets('a decision that has already been made offers nothing', (
    tester,
  ) async {
    await pumpList(tester, [
      _registration(
        ulid: '01CCCCCCCCCCCCCCCCCCCCCCCC',
        name: 'Ngaihte',
        status: 'rejected',
      ),
    ]);

    expect(find.text('Refused'), findsOneWidget);
    expect(find.text('Approve'), findsNothing);
    expect(find.text('Withdraw'), findsNothing);
  });
}
