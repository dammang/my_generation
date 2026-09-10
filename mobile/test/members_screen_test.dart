import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/core/constants/countries.dart';
import 'package:my_generation/features/clans/view/members_screen.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

const _jk = '01JKJKJKJKJKJKJKJKJKJKJKJK';
const _other = '01OTHEROTHEROTHEROTHEROTH';

Map<String, dynamic> _scope(String ulid, String name) => {
  'scope_type': 'clan',
  'scope_ulid': ulid,
  'name': name,
  'assignable_roles': <String>[],
};

Map<String, dynamic> _member({String name = 'Cing Za Man'}) => {
  'ulid': '01MEMBERMEMBERMEMBERMEMBER',
  'status': 'active',
  'scope': {'type': 'clan', 'ulid': _jk, 'name': 'JK'},
  'user': {'ulid': '01USERUSERUSERUSERUSERUSER', 'name': 'ngaih kim'},
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
  List<Map<String, dynamic>>? scopes,
  List<Map<String, dynamic>>? members,
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 1400));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'GET /api/v1/scope-roles/administered': [
      FakeReply(200, {
        'success': true,
        'data': scopes ?? [_scope(_jk, 'JK')],
      }),
    ],
    'GET /api/v1/scope-members': [
      FakeReply(200, {
        'success': true,
        'data': members ?? [_member()],
      }),
    ],
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: const MaterialApp(home: MembersScreen()),
    ),
  );

  await tester.pumpAndSettle();

  return adapter;
}

void main() {
  testWidgets('a member is listed with what they told us', (tester) async {
    final adapter = await _pump(tester);

    expect(find.text('Cing Za Man'), findsOneWidget);
    expect(find.text('Thawng Dam'), findsOneWidget);
    expect(find.text('cing@example.com'), findsOneWidget);

    // The country was stored as a code; a reader wants the country.
    expect(find.text('Myanmar (Burma)'), findsOneWidget);
    expect(find.text('MM'), findsNothing);

    // Members, not applicants: the queue of requests is its own page.
    final asked = adapter.received.singleWhere(
      (r) => r.path == '/api/v1/scope-members',
    );

    expect(asked.queryParameters['status'], 'active');
    expect(asked.queryParameters['scope_ulid'], _jk);
  });

  testWidgets('the name they signed up with is shown when it differs', (
    tester,
  ) async {
    await _pump(tester);

    expect(find.text('Signed in as ngaih kim'), findsOneWidget);
  });

  testWidgets('no filter is offered when there is only one clan', (
    tester,
  ) async {
    await _pump(tester);

    expect(find.byType(SegmentedButton<String>), findsNothing);
  });

  testWidgets('a second clan can be chosen between', (tester) async {
    // "Next time it will add more clan" — the filter appears when it is worth
    // having, not before.
    await _pump(tester, scopes: [_scope(_jk, 'JK'), _scope(_other, 'Sukte')]);

    expect(find.byType(SegmentedButton<String>), findsOneWidget);
    expect(find.text('Sukte'), findsOneWidget);
  });

  testWidgets('somebody who runs nothing is told so', (tester) async {
    await _pump(tester, scopes: []);

    expect(find.text('You do not run a family yet.'), findsOneWidget);
  });

  test('every country in the picker has a code and a name', () {
    expect(Countries.byCode.length, greaterThan(200));
    expect(Countries.nameOf('MM'), 'Myanmar (Burma)');
    expect(Countries.nameOf('mm'), 'Myanmar (Burma)');
    expect(Countries.nameOf('ZZ'), isNull);
    expect(Countries.matching('myan').single.key, 'MM');
    expect(Countries.matching('mm').single.key, 'MM');
  });
}
