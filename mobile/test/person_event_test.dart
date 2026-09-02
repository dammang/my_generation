import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/models/person_event.dart';

PersonEvent event(Map<String, dynamic> overrides) => PersonEvent.fromJson({
  'ulid': 'E1',
  'type': {'slug': 'birth', 'label': 'Birth', 'category': 'life'},
  'date_precision': 'exact',
  ...overrides,
});

void main() {
  group('PersonEvent', () {
    test('uses the contributor\'s title when they wrote one', () {
      expect(event({'title': 'Born at Hakha'}).heading, 'Born at Hakha');
    });

    test('falls back to the event type when the title is blank', () {
      expect(event({'title': '   '}).heading, 'Birth');
      expect(event({'title': null}).heading, 'Birth');
    });

    test('separates a guessed date from a merely coarse one', () {
      // "1926" recorded to year precision is known, not doubtful. Treating
      // every non-exact date as uncertain would mark most of a real archive
      // as unreliable.
      expect(event({'date_precision': 'exact'}).isUncertain, isFalse);
      expect(event({'date_precision': 'year'}).isUncertain, isFalse);
      expect(event({'date_precision': 'month'}).isUncertain, isFalse);

      expect(event({'date_precision': 'about'}).isUncertain, isTrue);
      expect(event({'date_precision': 'before'}).isUncertain, isTrue);
      expect(event({'date_precision': 'decade'}).isUncertain, isTrue);
      expect(event({'date_precision': 'between'}).isUncertain, isTrue);
    });

    test('hides a date line that only repeats the year', () {
      // The gutter already shows 1926; repeating it beside the title is noise.
      expect(
        event({'year': 1926, 'date_display': '1926', 'date_precision': 'year'})
            .dateDetail,
        isNull,
      );
    });

    test('keeps a date line that says more than the year', () {
      expect(
        event({
          'year': 1926,
          'date_display': 'abt. 1926',
          'date_precision': 'about',
        }).dateDetail,
        'abt. 1926',
      );
      expect(
        event({
          'year': 1948,
          'date_display': 'March 1948',
          'date_precision': 'month',
        }).dateDetail,
        'March 1948',
      );
    });

    test('renders a migration as a journey between two places', () {
      final migration = event({
        'type': {
          'slug': 'migration',
          'label': 'Migration',
          'category': 'movement',
        },
        'from_place': {'name': 'Chin Hills'},
        'to_place': {'name': 'Kalaymyo'},
      });

      expect(migration.isMigration, isTrue);
      expect(migration.placeLine, 'Chin Hills → Kalaymyo');
    });

    test('still renders a migration with only one end recorded', () {
      // Half a journey is a normal record: somebody knows where the family
      // arrived but not where they set out from.
      final migration = event({
        'to_place': {'name': 'Kalaymyo'},
      });

      expect(migration.placeLine, 'somewhere → Kalaymyo');
    });

    test('shows the single place for anything that is not a move', () {
      expect(
        event({
          'place': {'name': 'Hakha'},
        }).placeLine,
        'Hakha',
      );
      expect(event({}).placeLine, isNull);
    });
  });

  group('Timeline', () {
    test('separates empty from withheld', () {
      // The distinction the UI depends on: an empty timeline invites a
      // contribution, a withheld one must not.
      const empty = Timeline(events: [], withheld: false);
      const hidden = Timeline.withheldFrom();

      expect(empty.isEmpty, isTrue);
      expect(empty.withheld, isFalse);

      expect(hidden.isEmpty, isFalse);
      expect(hidden.withheld, isTrue);
    });
  });
}
