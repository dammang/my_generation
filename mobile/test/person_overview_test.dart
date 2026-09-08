import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/view/person_screen.dart';
import 'package:my_generation/models/person_detail.dart';
import 'package:my_generation/models/tree_summary.dart';
import 'package:my_generation/providers/app_providers.dart';
import 'package:my_generation/providers/person_provider.dart';
import 'package:my_generation/providers/tree_provider.dart';

import 'support/fake_api.dart';

const _ulid = '01KIPTUNKIPTUNKIPTUNKIPTUN';

PersonDetail _kipTun() => PersonDetail.fromJson({
  'ulid': _ulid,
  'display_name': 'KIP TUN',
  'gender': 'male',
  'is_living': true,
  'redacted': false,
  'tribe': {'ulid': '01TRIBETRIBETRIBETRIBETRIB', 'name': 'ZOMI'},
  'clan': {'ulid': '01CLANCLANCLANCLANCLANCLAN', 'name': 'JK'},
  'generation_label': '11th Generation',
  'generation': {
    'number': 11,
    'origin': 'JASUAN',
    'outer_number': 21,
    'outer_origin': 'PU ZO',
  },
});

TreeSummary _family() => TreeSummary.fromJson({
  'person': {
    'ulid': _ulid,
    'display_name': 'KIP TUN',
    'gender': 'male',
    'is_living': true,
    'redacted': false,
  },
  'generations': [
    {'depth': 1, 'male': 7, 'female': 2, 'unknown': 0, 'total': 9},
    {'depth': 2, 'male': 18, 'female': 13, 'unknown': 0, 'total': 31},
    {'depth': 3, 'male': 24, 'female': 19, 'unknown': 0, 'total': 43},
  ],
  'total': 83,
  'hidden': 0,
});

Future<void> _pump(
  WidgetTester tester, {
  PersonDetail? person,
  TreeSummary? family,
}) async {
  await tester.binding.setSurfaceSize(const Size(402, 1600));
  addTearDown(() => tester.binding.setSurfaceSize(null));

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(FakeAdapter({}))),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
        personProvider(_ulid).overrideWith((ref) async => person ?? _kipTun()),
        if (family != null)
          treeSummaryProvider(_ulid).overrideWith((ref) async => family),
      ],
      child: const MaterialApp(home: PersonScreen(ulid: _ulid)),
    ),
  );

  await tester.pumpAndSettle();
}

void main() {
  testWidgets('a generation says who it is counted from', (tester) async {
    await _pump(tester, family: _family());

    // "11th Generation" on its own does not say from whom, and this archive
    // keeps two scales ten generations apart: the same man is 21st from Pu Zo
    // and 11th of Jasuan.
    expect(
      find.text('21st generation from PU ZO\n11th generation of JASUAN'),
      findsOneWidget,
    );
    expect(find.text('11th of JASUAN'), findsOneWidget);
  });

  testWidgets('the family is counted at every remove', (tester) async {
    await _pump(tester, family: _family());

    expect(find.text('Children'), findsOneWidget);
    expect(find.text('7 sons, 2 daughters'), findsOneWidget);
    expect(find.text('Grandchildren'), findsOneWidget);
    expect(find.text('18 grandsons, 13 granddaughters'), findsOneWidget);
    expect(find.text('Great-grandchildren'), findsOneWidget);
    expect(
      find.text('24 great-grandsons, 19 great-granddaughters'),
      findsOneWidget,
    );
    expect(find.text('In all'), findsOneWidget);
    expect(find.text('83 people'), findsOneWidget);
  });

  testWidgets('a family nobody has counted yet says nothing', (tester) async {
    // Rather than a row of zeroes: "no children" about a man with eleven of
    // them, because the count had not arrived, is worse than saying nothing.
    await _pump(tester);

    // "Family" is the tab beside Overview, so the heading has to be checked
    // by something only this section says.
    expect(find.text('Descendants'), findsNothing);
    expect(find.text('Children'), findsNothing);
  });

  testWidgets('people the reader may not see are named, not subtracted', (
    tester,
  ) async {
    await _pump(
      tester,
      family: TreeSummary.fromJson({
        'person': {
          'ulid': _ulid,
          'display_name': 'KIP TUN',
          'gender': 'male',
          'is_living': true,
          'redacted': false,
        },
        'generations': [
          {'depth': 1, 'male': 2, 'female': 1, 'unknown': 0, 'total': 3},
        ],
        'total': 3,
        'hidden': 4,
      }),
    );

    expect(find.text('Not shown to you'), findsOneWidget);
    expect(find.text('4'), findsOneWidget);
  });
}
