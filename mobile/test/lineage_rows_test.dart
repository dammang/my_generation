import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/export/lineage_rows.dart';
import 'package:my_generation/models/person_summary.dart';

PersonSummary _p(String name) => PersonSummary.fromJson({
  'ulid': name,
  'display_name': name,
  'gender': 'male',
  'is_living': false,
  'redacted': false,
});

List<PersonSummary> _line(List<String> names) =>
    names.map(_p).toList(growable: false);

List<(String?, String?)> _names(List<LineageRow> rows) => [
  for (final row in rows) (row.person?.displayName, row.mothers?.displayName),
];

void main() {
  test('her father sits beside his grandfather, not beside him', () {
    final rows = LineageRow.pair(
      _line(['Grandfather', 'Father', 'Me']),
      _line(['Her father', 'Mother']),
    );

    expect(_names(rows), [
      ('Grandfather', 'Her father'),
      ('Father', 'Mother'),
      ('Me', null),
    ]);
  });

  test('a longer line on her side adds rows above his', () {
    final rows = LineageRow.pair(
      _line(['Father', 'Me']),
      _line([
        'Her great-grandfather',
        'Her grandfather',
        'Her father',
        'Mother',
      ]),
    );

    expect(_names(rows), [
      (null, 'Her great-grandfather'),
      (null, 'Her grandfather'),
      (null, 'Her father'),
      ('Father', 'Mother'),
      ('Me', null),
    ]);
  });

  test('no mother recorded leaves his line exactly as it was', () {
    final rows = LineageRow.pair(_line(['Grandfather', 'Father', 'Me']), []);

    expect(_names(rows), [
      ('Grandfather', null),
      ('Father', null),
      ('Me', null),
    ]);
  });
}
