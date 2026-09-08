import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/view/my_generation_screen.dart';
import 'package:my_generation/models/api_user.dart';
import 'package:my_generation/providers/app_providers.dart';
import 'package:my_generation/providers/auth_provider.dart';

import 'support/fake_api.dart';

const _me = '01MEMEMEMEMEMEMEMEMEMEMEME';

Map<String, dynamic> _person(
  String ulid,
  String name, {
  int? outer,
  int? inner,
}) => {
  'ulid': ulid,
  'display_name': name,
  'gender': 'male',
  'is_living': false,
  'redacted': false,
  'generation': {
    'number': inner,
    'origin': inner == null ? null : 'JASUAN',
    'outer_number': outer,
    'outer_origin': outer == null ? null : 'Pu Zo',
  },
};

class _StubAuth extends AuthNotifier {
  _StubAuth(this._personUlid);

  final String? _personUlid;

  @override
  AuthState build() => AuthSignedIn(
    ApiUser.fromJson({
      'ulid': '01USER',
      'name': 'Dam Mang',
      'email': 'dam@example.com',
      'permissions': <String>[],
      'scopes': <String, dynamic>{},
      'person': _personUlid == null
          ? null
          : {'ulid': _personUlid, 'display_name': 'Nang Lam Thang'},
    }),
  );
}

Future<void> pumpLine(
  WidgetTester tester, {
  String? personUlid = _me,
  List<Map<String, dynamic>>? line,
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 900));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'GET /api/v1/tree/$_me/line': [
      FakeReply(200, {
        'success': true,
        'data':
            line ??
            [
              _person('01A', 'PU ZO', outer: 1),
              _person('01B', 'KIP MANG', outer: 2),
              _person('01C', 'JASUAN', outer: 11, inner: 1),
              _person(_me, 'NANG LAM THANG', outer: 12, inner: 2),
            ],
      }),
    ],
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        authProvider.overrideWith(() => _StubAuth(personUlid)),
      ],
      child: const MaterialApp(home: MyGenerationScreen()),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('the line reads from the oldest down to me', (tester) async {
    await pumpLine(tester);

    expect(find.text('PU ZO'), findsOneWidget);
    expect(find.text('NANG LAM THANG'), findsOneWidget);

    // Both scales, in their own columns, headed by who they count from.
    expect(find.text('From Pu Zo'), findsOneWidget);
    expect(find.text('From JASUAN'), findsOneWidget);

    // Jasuan is the eleventh from Pu Zo and the first of his own line. Both
    // numbers are true and the table shows them side by side, so the check is
    // per row: "1st generation" appears twice on this screen and both are
    // right.
    Finder cellsOf(String name) => find.descendant(
      of: find.ancestor(of: find.text(name), matching: find.byType(Card)),
      matching: find.byType(Text),
    );

    final jasuan = tester
        .widgetList<Text>(cellsOf('JASUAN'))
        .map((t) => t.data)
        .toList();

    expect(jasuan, containsAll(['11th generation', '1st generation']));

    final puZo = tester
        .widgetList<Text>(cellsOf('PU ZO'))
        .map((t) => t.data)
        .toList();

    expect(puZo, containsAll(['1st generation', '—']));
  });

  testWidgets('above the origin the second column is a dash', (tester) async {
    await pumpLine(tester);

    // Not a blank: the clan does not count above its origin, and an empty
    // cell reads as data somebody forgot to enter.
    expect(find.text('—'), findsNWidgets(2));
  });

  testWidgets('the line ends where I am, and says so', (tester) async {
    await pumpLine(tester);

    expect(find.text("I'M HERE"), findsOneWidget);
  });

  testWidgets('an account with no record of its own is told what to do', (
    tester,
  ) async {
    await pumpLine(tester, personUlid: null);

    expect(find.text('Find yourself in the archive first'), findsOneWidget);
    expect(find.text("I'M HERE"), findsNothing);
  });
}
