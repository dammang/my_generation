import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/app.dart';
import 'package:my_generation/models/api_user.dart';
import 'package:my_generation/providers/app_providers.dart';
import 'package:my_generation/providers/auth_provider.dart';
import 'package:my_generation/providers/onboarding_provider.dart';
import 'package:my_generation/providers/sync_provider.dart';
import 'package:my_generation/providers/tree_provider.dart';
import 'package:my_generation/repositories/sync_queue_repository.dart';

import 'support/fake_api.dart';

ApiUser _user() => ApiUser.fromJson({
  'ulid': '01USER',
  'name': 'Dam Mang',
  'email': 'dam@example.com',
  'email_verified': true,
  'is_super_admin': false,
  'permissions': <String>[],
  'scopes': <String, dynamic>{},
});

PendingWrite _write(int id, String status) => PendingWrite(
  id: id,
  clientOperationId: 'op-$id',
  kind: 'person.update',
  payload: const {},
  status: status,
  attempts: 1,
);

/// The whole app, so the bar under test is the one the router actually builds
/// rather than a copy of it assembled for the test.
Future<void> pumpApp(
  WidgetTester tester, {
  SyncState sync = const SyncState(),
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 874));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(FakeAdapter({}))),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        authProvider.overrideWith(_StubAuth.new),
        // Already a member, so the router does not divert to the join screen.
        needsOnboardingProvider.overrideWith((ref) async => false),
        myMembershipsProvider.overrideWith((ref) async => []),
        myClaimsProvider.overrideWith((ref) async => []),
        // Always stubbed, never merely defaulted: the real controller opens a
        // real drift database on construction, and one per test is how you
        // end up with several of them sharing an executor.
        syncControllerProvider.overrideWith(() => _StubSync(sync)),
      ],
      child: const MyGenerationApp(),
    ),
  );
  await tester.pumpAndSettle();
}

class _StubAuth extends AuthNotifier {
  @override
  AuthState build() => AuthSignedIn(_user());
}

class _StubSync extends SyncController {
  _StubSync(this._state);

  final SyncState _state;

  @override
  SyncState build() => _state;
}

void main() {
  testWidgets('every section is reachable from the bottom bar', (tester) async {
    await pumpApp(tester);

    // Five destinations, not a hub with four hidden spokes.
    for (final label in ['Home', 'Tree', 'Edits', 'Outbox', 'Profile']) {
      expect(
        find.text(label),
        findsOneWidget,
        reason: '$label is missing from the bar',
      );
    }
  });

  testWidgets(
    'tapping a destination switches section without leaving the bar',
    (tester) async {
      await pumpApp(tester);

      expect(find.text('Welcome back, Dam'), findsOneWidget);

      await tester.tap(find.text('Profile'));
      await tester.pumpAndSettle();

      expect(find.text('What this account can reach'), findsOneWidget);
      // The bar has to survive the move, or the section it moved to is a
      // dead end reachable only by the back button — which is the whole
      // problem the bar exists to solve.
      expect(find.byType(NavigationBar), findsOneWidget);

      await tester.tap(find.text('Home'));
      await tester.pumpAndSettle();

      expect(find.text('Welcome back, Dam'), findsOneWidget);
    },
  );

  testWidgets('signing out is confirmed rather than done on a stray tap', (
    tester,
  ) async {
    await pumpApp(tester);

    await tester.tap(find.text('Profile'));
    await tester.pumpAndSettle();

    final signOut = find.widgetWithText(OutlinedButton, 'Sign out');

    await tester.scrollUntilVisible(signOut, 200);
    await tester.tap(signOut);
    await tester.pumpAndSettle();

    expect(find.text('Stay signed in'), findsOneWidget);
  });

  testWidgets('the bar gets out of the way while the tree is being moved', (
    tester,
  ) async {
    await pumpApp(tester);

    final container = ProviderScope.containerOf(
      tester.element(find.byType(NavigationBar)),
    );

    Offset offset() => tester
        .widget<AnimatedSlide>(
          find.ancestor(
            of: find.byType(NavigationBar),
            matching: find.byType(AnimatedSlide),
          ),
        )
        .offset;

    expect(offset(), Offset.zero);

    // Only on the tree's own tab: a bar that came and went everywhere would be
    // one nobody could rely on finding.
    container.read(treeIsMovingProvider.notifier).moving(true);
    await tester.pump();

    expect(offset(), Offset.zero, reason: 'home is not the chart');

    await tester.tap(find.text('Tree'));
    await tester.pumpAndSettle();

    expect(offset(), const Offset(0, 1));

    container.read(treeIsMovingProvider.notifier).moving(false);
    await tester.pump();

    expect(offset(), Offset.zero, reason: 'it has to come back on its own');
  });

  testWidgets('the chart paints the strip the bar sits on', (tester) async {
    await pumpApp(tester);

    bool extendsBody() =>
        tester.widgetList<Scaffold>(find.byType(Scaffold)).first.extendBody;

    // Sliding the bar away only helps if there is something behind it. Without
    // this the Scaffold holds that band open, the tree stops short of it, and
    // hiding the bar reveals an empty grey strip instead of more family.
    expect(extendsBody(), isFalse, reason: 'home is a list, not a canvas');

    await tester.tap(find.text('Tree'));
    await tester.pumpAndSettle();

    expect(extendsBody(), isTrue);

    // Back off the chart it stops again, or every other section would scroll
    // its last row underneath the bar.
    await tester.tap(find.text('Profile'));
    await tester.pumpAndSettle();

    expect(extendsBody(), isFalse);
  });

  group('the outbox badge', () {
    testWidgets('is absent when nothing is queued', (tester) async {
      await pumpApp(tester, sync: const SyncState());

      expect(find.byType(Badge), findsNothing);
    });

    testWidgets('counts everything still on the device', (tester) async {
      await pumpApp(
        tester,
        sync: SyncState(pending: [_write(1, 'pending'), _write(2, 'rejected')]),
      );

      // Both, not just the ones still expected to succeed: a refusal is the
      // one that will sit there forever unless somebody looks at it.
      expect(find.widgetWithText(Badge, '2'), findsOneWidget);
    });
  });
}
