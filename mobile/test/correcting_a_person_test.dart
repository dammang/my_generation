import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/view/edit_person_screen.dart';
import 'package:my_generation/models/person_detail.dart';
import 'package:my_generation/providers/app_providers.dart';

import 'support/fake_api.dart';

const _ulid = '01PERSONPERSONPERSONPERSON';

PersonDetail _person({
  String name = 'CING ZA MAN',
  String gender = 'unknown',
  String? birth,
}) => PersonDetail.fromJson({
  'ulid': _ulid,
  'display_name': name,
  'gender': gender,
  'is_living': false,
  'redacted': false,
  'birth': {'display': birth},
  'tribe': {'ulid': '01TRIBETRIBETRIBETRIBETRIB', 'name': 'Zomi'},
});

Future<FakeAdapter> pumpEdit(WidgetTester tester, PersonDetail person) async {
  await tester.binding.setSurfaceSize(const Size(402, 1600));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  final adapter = FakeAdapter({
    'PATCH /api/v1/people/$_ulid': [
      FakeReply(200, {
        'success': true,
        'data': {'ulid': _ulid, 'display_name': 'x'},
      }),
    ],
    'GET /api/v1/generations': [
      FakeReply(200, {'success': true, 'data': <dynamic>[]}),
    ],
  });

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: MaterialApp(home: EditPersonScreen(detail: person)),
    ),
  );
  await tester.pumpAndSettle();

  return adapter;
}

/// The correction itself. The screen also reads the generation labels, so
/// "the only request" is not the same thing as "the one under test".
Map<String, dynamic> sentBy(FakeAdapter adapter) =>
    (patches(adapter).single.data as Map).cast<String, dynamic>();

Iterable<dynamic> patches(FakeAdapter adapter) =>
    adapter.received.where((r) => r.method == 'PATCH');

void main() {
  testWidgets('the name is offered whole, not split into a first word', (
    tester,
  ) async {
    await pumpEdit(tester, _person());

    // "CING ZA MAN" is three words and one name. The form used to take the
    // first as a first name and drop the rest, so correcting anybody quietly
    // proposed shortening them.
    expect(find.widgetWithText(TextFormField, 'CING ZA MAN'), findsOneWidget);
    expect(find.text('First name'), findsNothing);
  });

  testWidgets('correcting the name sends the whole name', (tester) async {
    final adapter = await pumpEdit(tester, _person());

    await tester.enterText(
      find.widgetWithText(TextFormField, 'CING ZA MAN'),
      'CING ZA MANG',
    );
    await tester.tap(find.text('Save the correction'));
    await tester.pumpAndSettle();

    expect(sentBy(adapter)['display_name'], 'CING ZA MANG');
  });

  testWidgets('gender can be set on a record that never had one', (
    tester,
  ) async {
    final adapter = await pumpEdit(tester, _person());

    await tester.tap(find.text('Female'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save the correction'));
    await tester.pumpAndSettle();

    expect(sentBy(adapter)['gender'], 'female');
  });

  testWidgets('a date can be taken back out', (tester) async {
    final adapter = await pumpEdit(tester, _person(birth: '1902'));

    await tester.enterText(find.widgetWithText(TextFormField, '1902'), '');
    await tester.tap(find.text('Save the correction'));
    await tester.pumpAndSettle();

    // A form that only sends what is filled in cannot undo its own typo: the
    // old value stayed and it reported that it had saved.
    final sent = sentBy(adapter);

    expect(sent.containsKey('birth'), isTrue);
    expect(sent['birth'], isNull);
  });

  testWidgets('saving nothing says so rather than claiming success', (
    tester,
  ) async {
    final adapter = await pumpEdit(tester, _person());

    await tester.tap(find.text('Save the correction'));
    await tester.pumpAndSettle();

    expect(patches(adapter), isEmpty);
    expect(find.text('Nothing has been changed yet.'), findsOneWidget);
  });
}
