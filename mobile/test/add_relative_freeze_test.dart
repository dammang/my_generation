import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/view/add_relative_screen.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

const _anchor = '01ANCHOR';

/// A 201 the client cannot parse: the write succeeded, but `person` is absent,
/// so building the result throws a TypeError rather than an ApiException.
///
/// This is the shape of the real incident. The server had recorded the person;
/// the client threw on the way back and left the form disabled behind a
/// spinner, so the contributor believed nothing had been saved.
final _unparseable = {
  'success': true,
  'data': {
    'created': {'people': 1, 'relationships': 1},
    'change_request': null,
  },
  'warnings': const <dynamic>[],
};

Future<void> pumpForm(WidgetTester tester, FakeAdapter adapter) async {
  await tester.binding.setSurfaceSize(const Size(402, 1400));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: const MaterialApp(
        home: AddRelativeScreen(anchorUlid: _anchor, anchorName: 'Robert'),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('a response the client cannot parse does not freeze the form', (
    tester,
  ) async {
    final adapter = FakeAdapter({
      'POST /api/v1/people/$_anchor/relatives': [FakeReply(201, _unparseable)],
    });

    await pumpForm(tester, adapter);

    await tester.enterText(
      find.widgetWithText(TextFormField, 'First name'),
      'William',
    );

    final submit = find.widgetWithText(FilledButton, 'Add to the family');
    await tester.ensureVisible(submit);
    await tester.pumpAndSettle();
    await tester.tap(submit);
    await tester.pumpAndSettle();

    // The request went out, so the write really did reach the server.
    expect(
      adapter.received.where((r) => r.method == 'POST'),
      isNotEmpty,
      reason: 'the write should still have been attempted',
    );

    // And the form came back. Before the fix _saving stayed true forever:
    // every control disabled, a spinner that never stopped, and no way out
    // except leaving the screen and losing what had been typed.
    final button = tester.widget<FilledButton>(find.byType(FilledButton).first);
    expect(
      button.onPressed,
      isNotNull,
      reason: 'the form must not be left permanently disabled',
    );

    // Told honestly: it may well have been saved, so "try again" is the wrong
    // advice and duplicating the person is the likely result of giving it.
    expect(find.textContaining('may have been recorded'), findsOneWidget);
  });
}
