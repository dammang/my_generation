import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/view/person_screen.dart';
import 'package:my_generation/models/api_user.dart';
import 'package:my_generation/models/person_detail.dart';
import 'package:my_generation/models/visibility_choice.dart';
import 'package:my_generation/providers/app_providers.dart';
import 'package:my_generation/providers/auth_provider.dart';
import 'package:my_generation/providers/person_provider.dart';

import 'support/fake_api.dart';

const _mine = '01MEMEMEMEMEMEMEMEMEMEMEME';
const _theirs = '01THEMTHEMTHEMTHEMTHEMTHEM';

PersonDetail _person(String ulid, {String level = 'clan'}) =>
    PersonDetail.fromJson({
      'ulid': ulid,
      'display_name': 'KIP TUN',
      'gender': 'male',
      'is_living': true,
      'redacted': false,
      'privacy_level': level,
    });

ApiUser _me() => ApiUser.fromJson({
  'ulid': '01USERUSERUSERUSERUSERUSER',
  'name': 'Joseph',
  'email': 'joseph@example.com',
  'person': {'ulid': _mine, 'display_name': 'KIP TUN'},
});

Future<FakeAdapter> _pump(WidgetTester tester, String ulid) async {
  await tester.binding.setSurfaceSize(const Size(402, 2200));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'PATCH /api/v1/people/$ulid/visibility': [
      FakeReply(200, {
        'success': true,
        'data': {'ulid': ulid, 'privacy_level': 'private'},
      }),
    ],
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        authProvider.overrideWith(() => _SignedIn()),
        personProvider(ulid).overrideWith((ref) async => _person(ulid)),
      ],
      child: MaterialApp(home: PersonScreen(ulid: ulid)),
    ),
  );

  await tester.pumpAndSettle();

  return adapter;
}

void main() {
  testWidgets('the four choices are offered on your own record', (
    tester,
  ) async {
    await _pump(tester, _mine);

    expect(find.text('Who can see me'), findsOneWidget);

    for (final choice in VisibilityChoice.values) {
      expect(find.text(choice.label), findsOneWidget);
    }

    // Four, not five: "anyone in the tribe" is a rung most people cannot tell
    // apart from the clan.
    expect(VisibilityChoice.values.length, 4);
    expect(find.text('My clan'), findsOneWidget);
  });

  testWidgets('nobody is offered the setting on somebody else', (tester) async {
    await _pump(tester, _theirs);

    expect(find.text('Who can see me'), findsNothing);
  });

  testWidgets('choosing one sends it and says so', (tester) async {
    final adapter = await _pump(tester, _mine);

    await tester.tap(find.text('Only me'));
    await tester.pumpAndSettle();

    // Found by name rather than taken as the last request: the descendants
    // panel fetches while this is in flight.
    final sent = adapter.received.where(
      (r) => r.method == 'PATCH' && r.path.endsWith('/visibility'),
    );

    expect(sent, hasLength(1));
    expect(sent.single.data, {'privacy_level': 'private'});
    expect(find.textContaining('Saved'), findsOneWidget);
  });

  test('an unknown level selects nothing rather than guessing', () {
    // 'tribe' is a real level this app does not offer. Showing the wrong
    // answer to "who can see me" is worse than showing none.
    expect(VisibilityChoice.fromWire('tribe'), isNull);
    expect(VisibilityChoice.fromWire(null), isNull);
    expect(VisibilityChoice.fromWire('private'), VisibilityChoice.onlyMe);
  });
}

class _SignedIn extends AuthNotifier {
  @override
  AuthState build() => AuthSignedIn(_me());
}
