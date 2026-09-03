# Security review and load test

Run at the end of Phase 15 against a database of **101,415 people and 202,769
edges**, seeded by `php artisan genealogy:seed-scale`.

## What the load test measured

Warm figures, three runs each, over HTTP against `php artisan serve` — so they
include routing, authentication, the viewer-scope resolve, the privacy
predicate, and serialisation. Not raw query time.

| Endpoint | Time | Grows with database? |
|---|---|---|
| `GET /tree/{ulid}` 4 up, 3 down | 14.3 ms | No — depth and node budget bound it |
| `GET /tree/{ulid}` 8 up, 6 down (400 nodes) | 16.4 ms | No — hit the node budget, reported truncated |
| `GET /people` (page of 25) | 15.7 ms | No — index seek plus limit |
| `GET /people/{ulid}` | 15.4 ms | No |
| `GET /people/{ulid}/family` | 25.6 ms | No — bounded by family size |
| `GET /people?q=` search | 32.2 ms | Sub-linear — prefix and fulltext indexes |

Traversal measured directly: **ascend 8 generations in 2.3 ms**, descend 6 in
1.4 ms. Node-level BFS over `family_edges`, so cost follows the size of the
answer rather than the size of the archive.

### What the load test found

**The person list was reading 107 ms per page.** MySQL walked the `sort_name`
index and filtered, so it read rows until it happened to find 25 the viewer was
allowed to see. A composite index putting the filter columns ahead of the sort
column turns the scan into a seek:

```
before  Index scan on people using idx_people_sort    106.6 ms
after   Index lookup using idx_people_browse            0.3 ms
```

**Tribe statistics were keyed on `graph_version`.** Versioning is right for a
cached subtree, where stale means wrong; it was the wrong instinct here. Every
genealogy write bumps the version, so an active tribe recomputed a dozen
full-table counts on the very next request — 417 ms at 101,000 people, and
linear from there. Now cached on a plain one-hour window.

### Extrapolating to a million

Every endpoint above is bounded by the size of its *answer*, not the archive:
depth-limited traversal, index seeks with a limit, and a node budget on the
tree. Those stay flat. The operations that are genuinely O(n) are the aggregate
counts, and they sit behind a cache and denormalised counters.

A true 1,000,000-row run was **not performed**: at the measured 5.5 MB per 645
people it needs roughly 8.5 GB, and this machine had 2.8 GB free. The shapes
are what the extrapolation rests on, not a wish.

## Security review

| Area | Finding |
|---|---|
| Authorization on writes | Every write is behind a form-request `authorize()`, a policy, or an explicit permission check. Audited method by method |
| **Organisation privacy** | **Was leaking.** Clan and family-branch listings were unscoped: any authenticated account could enumerate every tribe's clans and branches, with population counts and each branch's named apical ancestor. The people were protected; the skeleton was not. Now scoped by `visibleTo`, with route binding running the same predicate |
| Person privacy | Enforced in SQL (`scopeVisibleTo`) and again per field (`FieldMask`). 404 rather than 403, so a refusal never confirms a record exists |
| Rate limiting | Six buckets. Sign-in limited by IP *and* by email, so spraying one address across addresses is still throttled |
| Mass assignment | No model uses `$guarded = []`. Public identifiers are set explicitly, never mass-assigned |
| SQL injection | One interpolation in the whole codebase — a CTE column width computed from a class constant. Everything else is bound |
| Secrets | None in source. `.env` is ignored by git |
| Logging | No request bodies or tokens are logged. The token is never logged even in development |
| Idempotency | Every write is replay-safe when the client sends an operation id; a reused id with a different payload is refused rather than replayed |
| CORS | Now explicit and empty by default. The mobile app is not a browser and needs none; credentials are off because authentication is a bearer token |
| Token lifetime | Unlimited by default, deliberately. See below |
| Token storage | Platform keystore — Keychain, or Android's hardware-backed KeyStore. Never in the app database, which a backup can read |
| Local cache | Holds only what the server already showed that viewer, in the masked form it sent. Wiped on sign-out |

### On token expiry

Sanctum tokens do not expire by default here, which is a deliberate choice
rather than an oversight. The people this is built for open it a few times a
year — a grandmother checking a grandchild's birth year should not be signed
out because ninety days passed. A stolen token is answered by *sign out
everywhere* and by the keystore, not by expiring everybody who uses the app the
way it is meant to be used. `SANCTUM_TOKEN_MINUTES` sets a limit where a
deployment needs one.

## Before deploying

- `APP_DEBUG=false` and `APP_ENV=production`
- `CORS_ALLOWED_ORIGINS` set if a web client exists
- HTTPS terminated in front of the app; `TrustProxies` is already configured
- The scheduler running, for the idempotency-ledger prune
- Redis reachable — the viewer scope, tree subgraphs and statistics all cache
