import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:my_generation/features/clans/view/administer_screen.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

const _clanUlid = '01CLANCLANCLANCLANCLANCLAN';
const _personUlid = '01PERSONPERSONPERSONPERSON';

Map<String, dynamic> _ok(Object data) => {'success': true, 'data': data};

/// The clan as ClanResource sends it: `ancestor` is present and null until
/// somebody records one, which is the state every approved clan starts in.
Map<String, dynamic> _clan({Map<String, dynamic>? ancestor}) => {
  'ulid': _clanUlid,
  'name': 'Guite',
  'slug': 'guite',
  'native_name': null,
  'description': null,
  'depth': 1,
  'level_label': null,
  'status': 'active',
  'counts': {'people': 0},
  'ancestor': ancestor,
};

FakeAdapter _adapter({Map<String, dynamic>? ancestor}) => FakeAdapter({
  'GET /api/v1/clans/$_clanUlid': [
    FakeReply(200, _ok(_clan(ancestor: ancestor))),
  ],
  'GET /api/v1/scope-roles/administered': [
    FakeReply(
      200,
      _ok([
        {
          'scope_type': 'clan',
          'scope_ulid': _clanUlid,
          'name': 'Guite',
          'depth': 1,
          'assignable_roles': ['clan-admin', 'historian'],
        },
      ]),
    ),
  ],
  'GET /api/v1/scope-roles': [FakeReply(200, _ok(<dynamic>[]))],
  'POST /api/v1/people': [
    FakeReply(
      201,
      _ok({
        'ulid': _personUlid,
        'display_name': 'Thawng Dam',
        'gender': 'male',
        'is_living': false,
        'redacted': false,
      }),
    ),
  ],
  'PATCH /api/v1/clans/$_clanUlid': [
    FakeReply(
      200,
      _ok(_clan(ancestor: {'ulid': _personUlid, 'display_name': 'Thawng Dam'})),
    ),
  ],
});

/// Through a real router, because starting the tree ends by opening the person
/// — and a push into nothing is exactly the kind of dead end this screen is
/// meant to remove.
Future<void> pump(WidgetTester tester, FakeAdapter adapter) async {
  await tester.binding.setSurfaceSize(const Size(402, 874));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final router = GoRouter(
    routes: [
      GoRoute(
        path: '/',
        builder: (_, _) =>
            const AdministerScreen(scopeType: 'clan', scopeUlid: _clanUlid),
      ),
      GoRoute(
        path: '/person/:ulid',
        builder: (_, state) => Scaffold(
          body: Center(child: Text('record ${state.pathParameters['ulid']}')),
        ),
      ),
    ],
  );

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: MaterialApp.router(routerConfig: router),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('an empty clan asks for the ancestor it descends from', (
    tester,
  ) async {
    await pump(tester, _adapter());

    expect(find.text('Where this family begins'), findsOneWidget);
    expect(find.text('Start the family tree'), findsOneWidget);
  });

  testWidgets('starting the tree creates the person and records them', (
    tester,
  ) async {
    final adapter = _adapter();
    await pump(tester, adapter);

    await tester.tap(find.text('Start the family tree'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).first, 'Thawng Dam');
    await tester.tap(find.text('Start the tree here'));
    await tester.pumpAndSettle();

    final created = adapter.received
        .where((r) => r.method == 'POST' && r.path == '/api/v1/people')
        .single;

    // Created straight into the clan: the server refuses an ancestor who is
    // not in it, so anywhere else would fail on the second call.
    expect((created.data as Map)['clan_ulid'], _clanUlid);
    expect((created.data as Map)['display_name'], 'Thawng Dam');

    final recorded = adapter.received.where((r) => r.method == 'PATCH').single;

    expect((recorded.data as Map)['ancestor_person_ulid'], _personUlid);

    // And it ends on their record, because the next thing anybody wants is to
    // add their children, and that lives on the person's own page.
    expect(find.text('record $_personUlid'), findsOneWidget);
  });

  testWidgets('a clan that already begins somewhere opens them instead', (
    tester,
  ) async {
    await pump(
      tester,
      _adapter(ancestor: {'ulid': _personUlid, 'display_name': 'Thawng Dam'}),
    );

    expect(find.text('Start the family tree'), findsNothing);
    expect(find.text('Thawng Dam'), findsOneWidget);

    await tester.tap(find.text('Thawng Dam'));
    await tester.pumpAndSettle();

    expect(find.text('record $_personUlid'), findsOneWidget);
  });
}
