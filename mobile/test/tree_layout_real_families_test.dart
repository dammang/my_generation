import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/layout/tree_layout_engine.dart';
import 'package:my_generation/models/tree_graph.dart';

/// The layout, run against real graphs out of the archive.
///
/// Every hand-made fixture for this engine has eventually turned out to encode
/// the same assumption as the code it was checking — children named so that
/// alphabetical order happened to be birth order, siblings with no spouses,
/// families with nothing beneath them. Each looked like coverage and none of
/// them could fail.
///
/// These are the actual shapes, taken from the server and stripped by the same
/// privacy mask a stranger would get: ulids, depths and who is married or
/// descended from whom, with no name, date or place in any of them.
const _families = [
  'tree_jasuan',
  'tree_singbel',
  'tree_deep',
  'deep_sing_bel',
  'deep_thawng_dam',
  'deep_hau_neng',
  'deep_pu_zo',
];

void main() {
  for (final family in _families) {
    group(family, () {
      final json =
          jsonDecode(File('test/fixtures/$family.json').readAsStringSync())
              as Map<String, dynamic>;

      final graph = TreeGraph.fromResponse(json, const {});
      final layout = const TreeLayoutEngine().layout(graph);

      double xOf(String ulid) => layout.nodes[ulid]!.rect.left;

      List<String> rowOf(String ulid) {
        final top = layout.nodes[ulid]!.rect.top;

        return (layout.nodes.values.where((n) => n.rect.top == top).toList()
              ..sort((a, b) => a.rect.left.compareTo(b.rect.left)))
            .map((n) => n.ulid)
            .toList();
      }

      List<String> drawnChildren(TreeUnion union) =>
          union.childUlids.where(layout.nodes.containsKey).toList();

      test('children are drawn in the order the family names them', () {
        for (final union in graph.unions) {
          final children = drawnChildren(union);

          if (children.length < 2) continue;

          final byPosition = [...children]
            ..sort((a, b) => xOf(a).compareTo(xOf(b)));

          expect(byPosition, children, reason: 'in union ${union.ulid}');
        }
      });

      test('nobody stands between a husband and wife', () {
        for (final union in graph.unions) {
          if (union.partnerUlids.length != 2) continue;
          if (!union.partnerUlids.every(layout.nodes.containsKey)) continue;

          final row = rowOf(union.partnerUlids.first);
          final a = row.indexOf(union.partnerUlids[0]);
          final b = row.indexOf(union.partnerUlids[1]);

          expect(
            (a - b).abs(),
            1,
            reason:
                'the chart draws whoever is adjacent as a couple, so a gap '
                'here means a marriage on screen that never happened '
                '(${union.ulid})',
          );
        }
      });

      test('no outsider stands between two siblings', () {
        for (final union in graph.unions) {
          final children = drawnChildren(union);

          if (children.length < 2) continue;

          final row = rowOf(children.first);
          final first = row.indexOf(children.first);
          final last = row.indexOf(children.last);

          final between = row
              .sublist(first, last + 1)
              .where((u) => !children.contains(u))
              // A sibling's own husband or wife belongs there.
              .where(
                (u) => !children.any(
                  (sibling) => graph.unions.any(
                    (m) =>
                        m.partnerUlids.contains(u) &&
                        m.partnerUlids.contains(sibling),
                  ),
                ),
              );

          expect(between, isEmpty, reason: 'in union ${union.ulid}');
        }
      });

      test('every parent stands over their own children', () {
        for (final union in graph.unions) {
          final children = drawnChildren(union);
          final parents = union.partnerUlids
              .where(layout.nodes.containsKey)
              .toList();

          if (children.isEmpty || parents.isEmpty) continue;

          final left = children.map(xOf).reduce((a, b) => a < b ? a : b);
          final right = children.map(xOf).reduce((a, b) => a > b ? a : b);

          for (final parent in parents) {
            // Room for the couple itself: a husband with two wives is placed
            // as one block, so a partner can sit a seat off the centre.
            expect(
              xOf(parent),
              inInclusiveRange(left - 500, right + 500),
              reason:
                  'a grandfather six cards from his own grandchildren is what '
                  'row-by-row packing produced (${union.ulid})',
            );
          }
        }
      });
    });
  }
}
