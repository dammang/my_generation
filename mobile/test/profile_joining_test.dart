import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/profile/view/profile_screen.dart';
import 'package:my_generation/models/api_user.dart';
import 'package:my_generation/providers/app_providers.dart';
import 'package:my_generation/providers/auth_provider.dart';

import 'support/fake_api.dart';

ApiUser _user({List<int> tribes = const [1], List<int> clans = const []}) =>
    ApiUser.fromJson({
      'ulid': '01USERUSERUSERUSERUSERUSER',
      'name': 'ngaih kim',
      'email': 'ngaihkim2021@example.com',
      'scopes': {'tribe_ids': tribes, 'clan_ids': clans},
    });

Future<void> _pump(WidgetTester tester, ApiUser user) async {
  await tester.binding.setSurfaceSize(const Size(402, 1600));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(
          fakeApiClient(
            FakeAdapter({
              // The page reads these on build; without answers the client is
              // left holding a timeout timer.
              'GET /api/v1/scope-roles/administered': [
                FakeReply(200, {'success': true, 'data': <dynamic>[]}),
              ],
              'GET /api/v1/memberships': [
                FakeReply(200, {'success': true, 'data': <dynamic>[]}),
              ],
            }),
          ),
        ),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        authProvider.overrideWith(() => _Signed(user)),
      ],
      child: const MaterialApp(home: ProfileScreen()),
    ),
  );

  await tester.pumpAndSettle();
}

void main() {
  testWidgets('joining is offered on the page, not behind a number', (
    tester,
  ) async {
    // Reported missing twice while it sat one tap under the "Clans"
    // statistic. A number is not where anybody looks for something to do, so
    // this asserts it is visible with nothing tapped.
    await _pump(tester, _user());

    expect(find.text('Not in a clan yet'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'Join a clan'), findsOneWidget);
  });

  testWidgets('somebody already in a clan is not nagged', (tester) async {
    await _pump(tester, _user(clans: const [7]));

    expect(find.widgetWithText(OutlinedButton, 'Join a clan'), findsNothing);
    expect(find.text('Not in a clan yet'), findsNothing);
  });
}

class _Signed extends AuthNotifier {
  _Signed(this.user);

  final ApiUser user;

  @override
  AuthState build() => AuthSignedIn(user);
}
