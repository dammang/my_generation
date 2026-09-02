# 03 — Genealogy Model & Traversal Engine

## 1. The graph, stated precisely

```
NODES      people
EDGES      relationships          directed: parent → child, guardian → ward
           unions                 undirected pair, itself an entity with attributes
           union_children         child ↦ union  (grouping, for chart layout + birth order)
PROJECTION family_edges           lean (parent_id, child_id, kind) derived from the above
```

Everything the UI calls a "tree" is a **query result**, not a stored structure.
The same person appears in thousands of different trees depending on who is at the root.

Derived, never stored:
- **siblings** = people sharing ≥1 parent edge (full siblings share ≥2)
- **spouses** = the other partner of each union
- **grandparents / descendants / cousins** = depth-N traversal
- **relative generation** = BFS depth from the chosen root

## 2. Why not the obvious alternatives

| Model | Why rejected |
|---|---|
| `people.parent_id` | Cannot express two parents, adoption, step-parents, unknown parentage, or disagreement. Non-starter. |
| `father_id` + `mother_id` on `people` | Breaks on adoptive + biological parents coexisting, same-sex parents, and competing claims. Also makes "who are this person's children" an unindexed scan across two columns. |
| Symmetric relationship rows (both directions stored) | Doubles writes and guarantees eventual drift between a row and its mirror. |
| Closure table over all people | Descendants of one tribal founder ≈ the whole tribe. Across many roots this is O(n²)-ish — hundreds of millions of rows, and a single insert high in the tree rewrites an enormous slice. |
| Nested sets / materialised path on people | Assumes a tree. Genealogy is a DAG (two parents, pedigree collapse). Structurally wrong. |
| Graph database in v1 | A second source of truth, a second consistency problem and a second ops burden, for a workload MySQL 8 recursive CTEs handle at this scale. Revisit at 5M+. |

## 3. The traversal queries

Both read only `family_edges`, whose two covering indexes make every level an
index-only scan.

### Ancestors (upward), depth-limited

```sql
WITH RECURSIVE anc (person_id, depth, path) AS (
    SELECT :root_id, 0, CAST(:root_id AS CHAR(2000))
    UNION ALL
    SELECT fe.parent_id,
           a.depth + 1,
           CONCAT(a.path, ',', fe.parent_id)
    FROM anc a
    JOIN family_edges fe ON fe.child_id = a.person_id
    WHERE a.depth < :max_depth
      AND FIND_IN_SET(fe.parent_id, a.path) = 0   -- cycle guard
)
SELECT DISTINCT person_id, MIN(depth) AS depth
FROM anc GROUP BY person_id
LIMIT :node_budget;
```

`FIND_IN_SET` against the accumulated path is the cycle guard. It costs little at
depth ≤ 8 and makes a corrupted edge (a data-entry loop that slipped past validation)
a slow query rather than an infinite recursion. `cte_max_recursion_depth` is also set
per-session as a hard backstop.

### Descendants (downward), depth-limited + fan-out capped

```sql
WITH RECURSIVE des (person_id, depth, path) AS (
    SELECT :root_id, 0, CAST(:root_id AS CHAR(2000))
    UNION ALL
    SELECT fe.child_id, d.depth + 1, CONCAT(d.path, ',', fe.child_id)
    FROM des d
    JOIN family_edges fe ON fe.parent_id = d.person_id
    WHERE d.depth < :max_depth
      AND FIND_IN_SET(fe.child_id, d.path) = 0
)
SELECT person_id, MIN(depth) AS depth
FROM des GROUP BY person_id
ORDER BY depth
LIMIT :node_budget;
```

Descendant fan-out is the dangerous direction: 4 generations × 5 children ≈ 780 people.
Three controls:

1. `max_depth` capped at `GENEALOGY_TREE_MAX_DEPTH` (default 8)
2. `node_budget` capped at `GENEALOGY_TREE_MAX_NODES` (default 800); `ORDER BY depth`
   means truncation drops the *furthest* generations first, which is what a user expects
3. Per-node `children_count` returned in `meta` so the client can render
   "+12 more children" and fetch that subtree on demand

**The UI never requests "everything".** Default initial load is 3 ancestors + 2
descendants from the focus person; expansion is incremental.

### Hydration without N+1

The CTEs return only ids. One query per entity type then loads the payload:

```php
$people  = Person::whereIn('id', $ids)->with(['profileMedia','birthPlace','tribe','clan'])->get();
$unions  = Union::where(fn($q) => $q->whereIn('partner_1_id',$ids)->orWhereIn('partner_2_id',$ids))->get();
$kids    = UnionChild::whereIn('union_id', $unions->pluck('id'))->orderBy('birth_order')->get();
$edges   = FamilyEdge::whereIn('parent_id',$ids)->whereIn('child_id',$ids)->get();
```

Fixed cost: 4 queries + 2 CTEs, regardless of tree size. Asserted by a test that fails
if `GET /api/v1/tree/{person}` exceeds a query-count budget.

## 4. Generation calculation

Two independent notions, deliberately not conflated:

**Relative generation** — computed at request time. The focus person (or a chosen
ancestor) is generation 0; the CTE's `depth` is the generation offset. Ancestors are
negative, descendants positive. Costs nothing extra; it falls out of the traversal.

**Absolute generation** — "17th generation of the Guite clan". Requires an agreed
origin: `family_branches.ancestor_person_id`, or a tribe/clan founder. Stored in
`lineage_depths` per (root, person), recomputed by a debounced job when edges beneath
that root change.

Pedigree collapse (cousins marrying — common in small clans) means a person can be
14 generations from the founder down one line and 16 down another. `lineage_depths`
stores `min_depth`, `max_depth` and `path_count`; the UI displays `min_depth` and shows
the range when they differ, rather than pretending there is one answer.

Optional `generations` rows only supply human labels. A missing or wrong generation
number degrades a caption; it never affects the tree.

## 5. Chart layout contract

The API returns a graph; the client lays it out. The response is shaped so a
Sugiyama-style layered layout is straightforward:

```json
{
  "focus": "01HZX…",
  "people":  [ { "ulid": "…", "depth": -1, "display_name": "Kin Tun", "…": "…" } ],
  "unions":  [ { "ulid": "…", "partners": ["…","…"], "children": ["…","…"],
                 "marriage_year": 1948, "order_index": 1 } ],
  "edges":   [ { "parent": "…", "child": "…", "kind": "biological" } ],
  "meta":    { "ancestors_depth": 3, "descendants_depth": 2,
               "truncated": true, "node_count": 214,
               "expandable": { "01HZX…": { "children": 12, "parents": 2 } } }
}
```

- `depth` gives the layer (row) directly.
- `unions[].children` ordered by `birth_order` gives sibling order within a layer.
- `expandable` tells the client exactly where to draw "+N more" affordances.
- Adoptive/step edges carry `kind`, so the client renders them dashed.

## 6. Add-relative UX → what actually happens in the database

The user picks a relation label; the `AddRelative` action translates it. All of this
is one transaction.

| User action on person **P** | Rows written |
|---|---|
| Add Father | `people`(new F); `relationships`(F→P, parent_child/biological); if P has a mother with a union, attach F to it, else create `unions`(F, null) + `union_children`(P) |
| Add Mother | mirror of the above |
| Add Spouse | `people`(new S); `unions`(P, S) normalised so `partner_1_id < partner_2_id`, `order_index` = P's union count + 1 |
| Add Son / Daughter | `people`(new C); if P has exactly one union → attach to it and write parent edges for **both** partners; if several → ask which; if none → create a single-parent union; `union_children`(C, birth_order) |
| Add Brother / Sister | if P has known parents → new person attached to P's parents' union (a real sibling edge is never written); if not → `relationships`(sibling_asserted), canonicalised `person_id < related_person_id` |
| Add Ancestor | same as Add Father/Mother, then optionally set as `family_branches.ancestor_person_id` |

Then, always: bump `tribes.graph_version`, dispatch `RebuildFamilyEdgesFor(person)`,
dispatch `RecomputeLineageDepth(root)` if under an apical ancestor, dispatch
`GeneratePersonMatchKeys`, write `revisions`, increment `contribution_stats`.

The contributor sees "Add Son". They never learn that a union row exists.

## 7. Duplicate detection

**Blocking, then scoring** — never all-pairs.

*Block* on shared `person_match_keys` (phonetic surname + birth decade, normalised full
name, name + birthplace, parent's name…). Two people are compared only if they share at
least one key. This turns O(n²) into O(n·k).

*Score* with weighted features, each contributing to a `signals` JSON so the reviewer
sees the reasoning:

| Feature | Weight |
|---|---|
| Name similarity (Jaro-Winkler over all `person_names`, best pair) | 0.30 |
| Phonetic match on surname + given name | 0.15 |
| Birth year within ±2 (exact = full weight, decade-precision = partial) | 0.20 |
| Death year within ±2 | 0.10 |
| Shared/nearby birth place (same place, or same parent place) | 0.10 |
| Shared parent (by identity or by name match) | 0.10 |
| Shared spouse | 0.05 |

Above `GENEALOGY_DUPLICATE_THRESHOLD` (0.82) → a `duplicate_candidates` row and a
notification to the scope's admins. **Nothing merges automatically, at any score.**

Phonetics: `metaphone` alone is tuned for English. A small transliteration ruleset for
Tedim/Zomi orthography runs first (`th`↔`t`, `kh`↔`k`, `zh`↔`z`, `aa`↔`a`, trailing
`-ng` normalisation, space/hyphen removal), then metaphone. The ruleset lives in
`config/genealogy.php` so it can be tuned per language without a deploy.

## 8. Merge

`MergePeople(winner, loser, fieldChoices)` — one transaction:

1. Snapshot the loser into `person_merges.loser_snapshot`
2. Apply per-field choices to the winner; record every change in `revisions`
3. Repoint every FK: relationships (both columns), unions (both), union_children,
   person_events, person_names, citations, story_people, media, saved_people,
   profile_claims, lineage_depths — each repoint logged in `moved_records`
4. De-duplicate the resulting edges (both parents may now point at the same child twice)
5. Soft-delete the loser, set `merged_into_person_id = winner.id`
6. Bump `graph_version`, queue edge/lineage/match-key rebuilds
7. Mark the `duplicate_candidates` row `merged`

Reversal replays `moved_records` backwards and restores `loser_snapshot`. Because the
loser row survives soft-deleted, old ULIDs and share links resolve to the winner (301)
rather than 404.

## 9. Cycle prevention

Before writing a parent→child edge, `AssertNoCycle` walks upward from the proposed
parent, depth-capped, looking for the proposed child. If found, the write is rejected
with a 422 naming the offending path — this is one of the few **hard** errors, because
a cycle makes every downstream traversal incorrect, not merely doubtful.
