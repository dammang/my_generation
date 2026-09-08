---
paths:
  - 'app/Actions/**'
---

# Actions

## Mass updates on Person skip the observer that keeps counters true
`Person::whereIn(...)->update([...])` writes the rows and fires no model event, so PersonObserver never adjusts `people_count` on tribes/clans/family_branches and never bumps the graph version. The Thawng Dam branch sat at 101 over 283 actual people this way, and every screen reads the counter.

After any mass update that moves people between a tribe, clan or branch, recount from the rows (see RecountBranch) or save through the models instead. Assert the counter against `count()` in the test — the drift is invisible otherwise.

Branch membership is directional: PlaceDescendantsInBranch only fills nulls. Releasing people who no longer descend from the founder is a separate step (ReleaseOutsidersFromBranch), and AnchorFamilyBranch does both.
