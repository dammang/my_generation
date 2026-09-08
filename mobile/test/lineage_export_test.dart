import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/export/lineage_export.dart';
import 'package:my_generation/models/person_summary.dart';

PersonSummary _person(String name, {int? outer, int? inner}) =>
    PersonSummary.fromJson({
      'ulid': name,
      'display_name': name,
      'gender': 'male',
      'is_living': false,
      'redacted': false,
      'generation': {
        'number': inner,
        'origin': inner == null ? null : 'JASUAN',
        'outer_number': outer,
        'outer_origin': outer == null ? null : 'Pu Zo',
      },
    });

LineageExport _export(int generations) => LineageExport(
  people: [
    for (var i = 1; i <= generations; i++)
      _person('ANCESTOR $i', outer: i, inner: i > 10 ? i - 10 : null),
  ],
  title: 'Nang Lam Thang — lineage',
  outerOrigin: 'Pu Zo',
  origin: 'JASUAN',
);

void main() {
  test('the document is a real pdf', () async {
    final bytes = await _export(4).documentBytes();

    // %PDF. A file the phone will not open is not an export.
    expect(String.fromCharCodes(bytes.take(4)), '%PDF');
  });

  test('a long line breaks across pages instead of running off one', () async {
    final short = await _export(4).documentBytes();
    final long = await _export(120).documentBytes();

    // The whole reason for a document rather than a picture: thirty
    // generations is not unusual and a hundred is possible.
    expect(long.length, greaterThan(short.length));

    // Discriminating: four rows are one sheet, a hundred and twenty are not.
    // "More than one page" alone would pass on a document that was always
    // paginated and never checked.
    expect(_pages(short), 1);
    expect(
      _pages(long),
      greaterThan(1),
      reason: 'a hundred and twenty rows fitted on one page, which they cannot',
    );
  });

  test('no character is silently dropped from the document', () async {
    // The font does not throw on a glyph it lacks; it warns and leaves a hole,
    // so the name prints short and nothing else reports a thing. This asserts
    // on the warning because the hole itself is invisible from here.
    final complaints = <String>[];

    await runZoned(
      () => LineageExport(
        people: [_person('Nang Lam Thang', outer: 12, inner: 2)],
        // The screen builds this title with an em dash, and the columns show a
        // dash for a generation that has no number.
        title: 'Nang Lam Thang \u2014 lineage',
        outerOrigin: 'Pu Zo',
        origin: 'JASUAN',
      ).documentBytes(),
      zoneSpecification: ZoneSpecification(
        print: (self, parent, zone, line) => complaints.add(line),
      ),
    );

    expect(
      complaints.where((line) => line.contains('Unable to find a font')),
      isEmpty,
      reason: 'a character was dropped from the document: $complaints',
    );
  });

  test('it is text, not a picture of text', () async {
    final bytes = await _export(4).documentBytes();
    final body = String.fromCharCodes(bytes);

    // A page of typeset rows is a few tens of kilobytes; the same thing as an
    // image is several megabytes and cannot be searched or zoomed into.
    expect(bytes.length, lessThan(400000));
    expect(body, isNot(contains('DCTDecode')));
  });
}

/// How many pages the document declares.
///
/// The negative lookahead matters: the page *tree* is `/Type /Pages`, one per
/// document, so counting the prefix alone reports one page too many and makes
/// "more than one page" true of every document ever written.
int _pages(List<int> bytes) => RegExp(
  r'/Type\s*/Page(?![s])',
).allMatches(String.fromCharCodes(bytes)).length;
