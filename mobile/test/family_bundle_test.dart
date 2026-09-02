import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/models/family_bundle.dart';

Map<String, dynamic> person(String ulid, String name) => {
  'ulid': ulid,
  'display_name': name,
  'gender': 'male',
  'is_living': false,
  'redacted': false,
};

void main() {
  group('FamilyUnion', () {
    test('names the other partner from one person\'s point of view', () {
      final union = FamilyUnion.fromJson({
        'ulid': 'U1',
        'partners': [person('P1', 'Thawng Dam'), person('P2', 'Ngun Hlei')],
        'children': const [],
        'union_type': 'marriage',
        'status': 'married',
      });

      expect(union.partnerOther('P1')?.displayName, 'Ngun Hlei');
      expect(union.partnerOther('P2')?.displayName, 'Thawng Dam');
    });

    test('returns nothing for a union with only one recorded partner', () {
      // A marriage where the spouse was never recorded is common in old
      // records, and must not crash the screen that renders it.
      final union = FamilyUnion.fromJson({
        'ulid': 'U1',
        'partners': [person('P1', 'Thawng Dam')],
        'children': const [],
        'union_type': 'marriage',
        'status': 'married',
      });

      expect(union.partnerOther('P1'), isNull);
    });

    test('describes a marriage by its dates when it has them', () {
      final union = FamilyUnion.fromJson({
        'ulid': 'U1',
        'partners': const [],
        'children': const [],
        'union_type': 'marriage',
        'status': 'divorced',
        'marriage': {'year': 1948, 'display': '1948', 'precision': 'exact'},
        'divorce_date': '1961-04-02',
      });

      expect(union.describe(), 'm. 1948 · ended 1961');
    });

    test('falls back to the status when no dates are recorded', () {
      final union = FamilyUnion.fromJson({
        'ulid': 'U1',
        'partners': const [],
        'children': const [],
        'union_type': 'marriage',
        'status': 'widowed',
      });

      expect(union.describe(), 'Widowed');
    });

    test('keeps the server count so hidden children are not lost', () {
      // children_count is the truth; the array is only what this viewer may
      // see. Rendering the array length as the total would tell somebody the
      // family is smaller than it is.
      final union = FamilyUnion.fromJson({
        'ulid': 'U1',
        'partners': const [],
        'children': [person('C1', 'Bawi')],
        'union_type': 'marriage',
        'status': 'married',
        'children_count': 4,
      });

      expect(union.children.length, 1);
      expect(union.childrenCount, 4);
    });
  });

  group('FamilyBundle', () {
    FamilyBundle bundleWith({
      required List<Map<String, dynamic>> children,
      required List<Map<String, dynamic>> unions,
    }) {
      return FamilyBundle.fromJson({
        'person': person('P1', 'Thawng Dam'),
        'parents': const [],
        'spouses': const [],
        'children': children,
        'siblings': const [],
        'unions': unions,
      });
    }

    test('finds children who belong to no recorded union', () {
      // Parents known, marriage not: the child must still appear somewhere, or
      // grouping by union silently deletes them from the screen.
      final bundle = bundleWith(
        children: [person('C1', 'Bawi'), person('C2', 'Sui')],
        unions: [
          {
            'ulid': 'U1',
            'partners': const [],
            'children': [person('C1', 'Bawi')],
            'union_type': 'marriage',
            'status': 'married',
          },
        ],
      );

      expect(bundle.unattachedChildren.map((c) => c.ulid), ['C2']);
    });

    test('treats every child as unattached when there are no unions', () {
      final bundle = bundleWith(
        children: [person('C1', 'Bawi')],
        unions: const [],
      );

      expect(bundle.unattachedChildren.length, 1);
    });

    test('reports an empty family', () {
      expect(bundleWith(children: const [], unions: const []).isEmpty, isTrue);
    });
  });
}
