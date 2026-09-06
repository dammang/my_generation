import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/core/theme/app_theme.dart';
import 'package:my_generation/features/tree/layout/tree_metrics.dart';
import 'package:my_generation/features/tree/widgets/tree_person_card.dart';
import 'package:my_generation/models/person_summary.dart';
import 'package:my_generation/models/tree_graph.dart';

PersonSummary _person({
  String name = 'Margaret Whitfield',
  String? birth = '1922',
  String? death = '2001',
}) => PersonSummary.fromJson({
  'ulid': '01AAA',
  'display_name': name,
  'gender': 'female',
  'is_living': false,
  'redacted': false,
  'verification_status': 'verified',
  'birth': {'display': birth},
  'death': {'display': death},
});

/// Renders one card in exactly the box the layout engine reserves for it.
///
/// That is the whole point: the card cannot grow, because its rect was decided
/// by the engine before the widget existed. Anything that does not fit is
/// clipped on a real device and throws here.
Future<void> pumpCard(
  WidgetTester tester,
  PersonSummary person, {
  double textScale = 1.0,
}) async {
  final metrics = TreeMetrics.forTextScale(TextScaler.linear(textScale));

  await tester.pumpWidget(
    MediaQuery(
      data: MediaQueryData(textScaler: TextScaler.linear(textScale)),
      child: MaterialApp(
        theme: AppTheme.light(),
        home: Scaffold(
          body: Center(
            child: SizedBox(
              width: metrics.cardWidth,
              height: metrics.cardHeight,
              child: TreePersonCard(
                person: person,
                isFocus: false,
                expandable: const Expandable(parents: 0, children: 0),
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

/// The dates must be *inside* the card, not merely present in the tree.
///
/// Asserting "no overflow exception" is not enough: the name is Flexible, so a
/// box that is too short truncates the name and reports nothing wrong while
/// the lifespan still hangs over the edge and is clipped on screen. This
/// compares the rendered rect of the dates against the card's own rect, which
/// is the thing that was actually wrong on iOS.
void expectDatesInsideCard(WidgetTester tester, String dates) {
  final card = tester.getRect(find.byType(TreePersonCard));
  final lifespan = tester.getRect(find.text(dates));

  expect(
    lifespan.bottom,
    lessThanOrEqualTo(card.bottom),
    reason: 'the dates hang $dates below the bottom of the card',
  );
  expect(lifespan.top, greaterThanOrEqualTo(card.top));
  expect(tester.takeException(), isNull);
}

void main() {
  testWidgets('the dates sit inside the card', (tester) async {
    await pumpCard(tester, _person());

    expectDatesInsideCard(tester, '1922–2001');
  });

  testWidgets('a two-line name does not push the dates out', (tester) async {
    await pumpCard(tester, _person(name: 'Margaret Elizabeth Whitfield'));

    expectDatesInsideCard(tester, '1922–2001');
  });

  testWidgets('a living person with one date fits', (tester) async {
    await pumpCard(tester, _person(name: 'Susan Whitfield', death: null));

    expectDatesInsideCard(tester, 'b. 1922');
  });

  group('with the type size turned up', () {
    for (final scale in [1.15, 1.3, 1.5]) {
      testWidgets('the dates still sit inside the card at ${scale}x', (
        tester,
      ) async {
        await pumpCard(
          tester,
          _person(name: 'Margaret Elizabeth Whitfield'),
          textScale: scale,
        );

        // The engine is told the same scale the card renders at, so the box
        // grows with the text rather than the text overflowing the box.
        expectDatesInsideCard(tester, '1922–2001');
      });
    }
  });

  test('the reserved height grows with the type size', () {
    final normal = TreeMetrics.forTextScale(const TextScaler.linear(1));
    final large = TreeMetrics.forTextScale(const TextScaler.linear(1.5));

    expect(large.cardHeight, greaterThan(normal.cardHeight));

    // Only the text scales. Scaling the whole box would give a card that is
    // mostly whitespace at large type sizes.
    expect(large.cardHeight, lessThan(normal.cardHeight * 1.5));
  });
}
