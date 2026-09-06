import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/clans/view/committee_screen.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

/// The shapes the server actually sends, copied from the resources rather
/// than invented here — a fake that encodes the client's own assumption
/// proves only that the client agrees with itself.
Map<String, dynamic> _ok(Object data) => {'success': true, 'data': data};

const _clanUlid = '01CLANCLANCLANCLANCLANCLAN';
const _cousinUlid = '01COUSINCOUSINCOUSINCOUSIN';

Map<String, List<FakeReply>> _replies({
  List<Map<String, dynamic>> appointments = const [],
}) => {
  'GET /api/v1/scope-roles/administered': [
    FakeReply(
      200,
      _ok([
        {
          'scope_type': 'clan',
          'scope_ulid': _clanUlid,
          'name': 'Guite',
          'depth': 1,
          // Deliberately without tribe-admin: a clan admin may not hand out
          // authority over the tribe their clan sits in.
          'assignable_roles': [
            'clan-admin',
            'historian',
            'contributor',
            'member',
          ],
        },
      ]),
    ),
  ],
  'GET /api/v1/scope-roles': [FakeReply(200, _ok(appointments))],
  'GET /api/v1/scope-roles/candidates': [
    FakeReply(
      200,
      _ok([
        {
          'user': {'ulid': _cousinUlid, 'name': 'Dam Mang'},
          'roles': <String>[],
        },
      ]),
    ),
  ],
  'POST /api/v1/scope-roles': [
    FakeReply(
      200,
      _ok({
        'user_ulid': _cousinUlid,
        'role': 'historian',
        'scope_type': 'clan',
        'scope_ulid': _clanUlid,
      }),
    ),
  ],
};

Future<FakeAdapter> pumpCommittee(
  WidgetTester tester, {
  List<Map<String, dynamic>> appointments = const [],
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 874));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter(_replies(appointments: appointments));

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: const MaterialApp(
        home: CommitteeScreen(scopeType: 'clan', scopeUlid: _clanUlid),
      ),
    ),
  );
  await tester.pumpAndSettle();

  return adapter;
}

void main() {
  testWidgets('an appointment says who made it', (tester) async {
    await pumpCommittee(
      tester,
      appointments: [
        {
          'user': {'ulid': _cousinUlid, 'name': 'Dam Mang'},
          'role': 'clan-admin',
          'granted_at': '2026-09-01 10:00:00',
          'granted_by': 'Nem Kim',
        },
      ],
    );

    expect(find.text('Guite'), findsOneWidget);
    expect(find.text('Dam Mang'), findsOneWidget);
    // Named, not slugged: "clan-admin" is a database value, and who appointed
    // somebody is the first thing anybody asks about an appointment.
    expect(
      find.text('Clan administrator · appointed by Nem Kim'),
      findsOneWidget,
    );
  });

  testWidgets('appointing sends what the server asked for', (tester) async {
    final adapter = await pumpCommittee(tester);

    expect(find.text('Nobody has been appointed here yet.'), findsOneWidget);

    await tester.tap(find.text('Appoint'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Dam Mang'));
    await tester.pumpAndSettle();

    // Only what this account may hand out. Offering a role the server will
    // then refuse is worse than never offering it.
    expect(find.text('Clan administrator'), findsOneWidget);
    expect(find.text('Tribe administrator'), findsNothing);

    await tester.tap(find.text('Historian'));
    await tester.pumpAndSettle();

    final post = adapter.received
        .where((r) => r.method == 'POST' && r.path == '/api/v1/scope-roles')
        .single;

    // Who, what and where — the four fields the grant is identified by, since
    // an appointment has no id of its own.
    expect(post.data, {
      'scope_type': 'clan',
      'scope_ulid': _clanUlid,
      'user_ulid': _cousinUlid,
      'role': 'historian',
    });
  });

  testWidgets('a refusal from the server is shown rather than swallowed', (
    tester,
  ) async {
    final adapter = FakeAdapter({
      ..._replies(),
      'POST /api/v1/scope-roles': [
        FakeReply(403, {
          'success': false,
          'message':
              'You may not grant a role carrying permissions you do not hold here: roles.assign.',
          'code': 'ROLE_ASSIGNMENT_FORBIDDEN',
        }),
      ],
    });

    await tester.binding.setSurfaceSize(const Size(402, 874));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
          secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        ],
        child: const MaterialApp(
          home: CommitteeScreen(scopeType: 'clan', scopeUlid: _clanUlid),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Appoint'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Dam Mang'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Historian'));
    await tester.pumpAndSettle();

    // The escalation guard is the whole point of the feature, so its refusal
    // has to reach the person who tried — silence here reads as success.
    expect(find.textContaining('You may not grant a role'), findsOneWidget);
  });
}
