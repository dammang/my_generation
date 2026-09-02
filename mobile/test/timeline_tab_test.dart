import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/person/widgets/timeline_tab.dart';
import 'package:my_generation/models/person_event.dart';

Widget wrap(Timeline timeline, {VoidCallback? onAdd}) => MaterialApp(
  home: Scaffold(
    body: TimelineTab(timeline: timeline, onAddEvent: onAdd ?? () {}),
  ),
);

PersonEvent event({
  required String slug,
  required String label,
  int? year,
  String precision = 'exact',
  String? display,
}) => PersonEvent.fromJson({
  'ulid': 'E-$slug-$year',
  'type': {'slug': slug, 'label': label, 'category': 'life'},
  'year': year,
  'date_display': display,
  'date_precision': precision,
});

void main() {
  testWidgets('an empty timeline invites a contribution', (tester) async {
    await tester.pumpWidget(wrap(const Timeline(events: [], withheld: false)));

    expect(find.text('Nothing recorded yet'), findsOneWidget);
    expect(find.text('Add an event'), findsOneWidget);
  });

  testWidgets('a withheld timeline does not invite one', (tester) async {
    await tester.pumpWidget(wrap(const Timeline.withheldFrom()));

    expect(find.text('This life story is private'), findsOneWidget);
    // The bug this guards against: rendering "withheld" as "empty" and asking
    // somebody to fill in a life they are not permitted to see.
    expect(find.text('Add an event'), findsNothing);
    expect(find.text('Nothing recorded yet'), findsNothing);
  });

  testWidgets('events render with their year in the gutter', (tester) async {
    await tester.pumpWidget(
      wrap(
        Timeline(
          events: [
            event(slug: 'birth', label: 'Birth', year: 1901),
            event(slug: 'death', label: 'Death', year: 1974),
          ],
          withheld: false,
        ),
      ),
    );

    expect(find.text('1901'), findsOneWidget);
    expect(find.text('1974'), findsOneWidget);
    expect(find.text('Birth'), findsOneWidget);
  });

  testWidgets('an uncertain date keeps its wording alongside the year', (
    tester,
  ) async {
    await tester.pumpWidget(
      wrap(
        Timeline(
          events: [
            event(
              slug: 'migration',
              label: 'Migration',
              year: 1902,
              precision: 'about',
              display: 'abt. 1902',
            ),
          ],
          withheld: false,
        ),
      ),
    );

    // Both appear: the year to sort and scan by, the wording so the reader
    // knows it is a guess.
    expect(find.text('1902'), findsOneWidget);
    expect(find.text('abt. 1902'), findsOneWidget);
  });

  testWidgets('a plain year shows no second date line', (tester) async {
    await tester.pumpWidget(
      wrap(
        Timeline(
          events: [
            event(slug: 'birth', label: 'Birth', year: 1901, display: '1901'),
          ],
          withheld: false,
        ),
      ),
    );

    // "1901" twice would be noise; the gutter already said it.
    expect(find.text('1901'), findsOneWidget);
  });

  testWidgets('an event with no year still renders', (tester) async {
    await tester.pumpWidget(
      wrap(
        Timeline(
          events: [event(slug: 'other', label: 'Left the village')],
          withheld: false,
        ),
      ),
    );

    expect(find.text('—'), findsOneWidget);
    expect(find.text('Left the village'), findsOneWidget);
  });
}
