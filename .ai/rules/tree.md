---
paths:
  - 'mobile/lib/features/tree/**'
---

# Tree

## Test the tree layout against real graphs, not hand-made ones
Every hand-made fixture for the layout engine has eventually encoded the same assumption as the code it checked — children named so alphabetical order was birth order, siblings with no spouses, families with nothing beneath them. Each looked like coverage and none could fail.

`mobile/test/fixtures/*.json` are real graphs exported through TreeResource with the privacy mask on (ulids, depths and who is married or descended from whom; no names, dates or places). `tree_layout_real_families_test.dart` asserts four things over them: children left-to-right in birth order, nobody between a husband and wife, no outsider between siblings, every parent over their own children. Add a case there before trusting a synthetic one.

Refresh a fixture by building the graph on the server and dumping `TreeResource::make($graph)`.
