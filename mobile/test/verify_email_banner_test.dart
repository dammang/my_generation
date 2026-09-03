import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/auth/widgets/verify_email_banner.dart';
import 'package:my_generation/models/api_user.dart';
import 'package:my_generation/providers/app_providers.dart';
import 'package:my_generation/providers/auth_provider.dart';

import 'support/fake_api.dart';

ApiUser _user({required bool verified}) => ApiUser.fromJson({
  'ulid': '01USER',
  'name': 'Dam Mang',
  'email': 'dam@example.com',
  'email_verified': verified,
  'is_super_admin': false,
  'permissions': <String>[],
  'scopes': <String, dynamic>{},
});

Map<String, dynamic> _meEnvelope({required bool verified}) => {
  'success': true,
  'data': _user(verified: verified).toJson(),
  'warnings': const <dynamic>[],
};

Future<void> pumpBanner(WidgetTester tester, FakeAdapter adapter, {required bool verified}) async {
  await tester.binding.setSurfaceSize(const Size(402, 874));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        authProvider.overrideWith(() => _StubAuth(_user(verified: verified))),
      ],
      child: const MaterialApp(home: Scaffold(body: VerifyEmailBanner())),
    ),
  );
  await tester.pumpAndSettle();
}

/// Signed in as a given account, without going through a real sign-in.
class _StubAuth extends AuthNotifier {
  _StubAuth(this._user);

  final ApiUser _user;

  @override
  AuthState build() => AuthSignedIn(_user);
}

void main() {
  testWidgets('a confirmed address is not nagged about', (tester) async {
    await pumpBanner(tester, FakeAdapter({}), verified: true);

    expect(find.text('Resend'), findsNothing);
  });

  testWidgets('an unconfirmed address is named, with a way to fix it', (tester) async {
    await pumpBanner(tester, FakeAdapter({}), verified: false);

    // Naming the address matters: somebody who mistyped it needs to see that.
    expect(find.textContaining('dam@example.com'), findsOneWidget);
    expect(find.text('Resend'), findsOneWidget);
  });

  testWidgets('resending says it worked', (tester) async {
    final adapter = FakeAdapter({
      'POST /api/v1/auth/email/resend': [
        const FakeReply(200, {'success': true, 'data': null, 'warnings': <dynamic>[]}),
      ],
      'GET /api/v1/auth/me': [FakeReply(200, _meEnvelope(verified: false))],
    });

    await pumpBanner(tester, adapter, verified: false);
    await tester.tap(find.text('Resend'));
    await tester.pumpAndSettle();

    expect(find.textContaining('Check your inbox'), findsOneWidget);
  });

  testWidgets('asking twice too quickly is explained, not alarming', (tester) async {
    final adapter = FakeAdapter({
      'POST /api/v1/auth/email/resend': [
        const FakeReply(429, {
          'success': false,
          'message': 'Too Many Attempts.',
          'errors': <String, dynamic>{},
          'code': 'THROTTLED',
        }),
      ],
    });

    await pumpBanner(tester, adapter, verified: false);
    await tester.tap(find.text('Resend'));
    await tester.pumpAndSettle();

    expect(find.textContaining('Give it a minute'), findsOneWidget);
  });
}
