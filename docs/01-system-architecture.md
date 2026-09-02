# 01 — System Architecture

## 1. Component map

```
┌──────────────────┐   ┌──────────────────┐   ┌──────────────────┐
│  Flutter mobile  │   │  Future web SPA  │   │  Filament admin  │
│  (iOS / Android) │   │   (same API)     │   │  (server-side)   │
└────────┬─────────┘   └────────┬─────────┘   └────────┬─────────┘
         │ Bearer token                  │              │ session + CSRF
         │ (Sanctum PAT)                 │              │
         └───────────────┬───────────────┘              │
                         ▼                              ▼
              ┌────────────────────────────────────────────┐
              │            Laravel application             │
              │                                            │
              │  routes/api.php (v1)   routes/web.php      │
              │        │                     │             │
              │  FormRequest → Policy → Controller         │
              │        │                                   │
              │  Domain services / Actions  ◄── Filament   │
              │        │                        Resources  │
              │  Eloquent models + Observers               │
              └───┬──────────┬───────────┬────────────┬────┘
                  │          │           │            │
                  ▼          ▼           ▼            ▼
            ┌─────────┐ ┌────────┐ ┌──────────┐ ┌──────────┐
            │  MySQL  │ │ Redis  │ │ S3 / R2  │ │  Queue   │
            │   8.4+  │ │ cache  │ │  media   │ │ workers  │
            │         │ │ +locks │ │          │ │ (Redis)  │
            └─────────┘ └────────┘ └──────────┘ └──────────┘
                                                      │
                                        ┌─────────────┴─────────────┐
                                        │ Scheduler: living-status  │
                                        │ recheck, duplicate scan,  │
                                        │ stats rollup, cache warm  │
                                        └───────────────────────────┘
```

**Deliberately excluded from v1:** no microservices, no GraphQL, no graph database,
no event-sourcing framework, no CQRS bus. A single well-indexed MySQL schema with
depth-limited recursive CTEs comfortably serves 1–5M people. We revisit only when
measurements say so (§7).

## 2. Layer responsibilities

| Layer | Responsibility | Must not |
|---|---|---|
| **Controller** | Parse → authorise → delegate → return Resource | Contain genealogy logic |
| **FormRequest** | Shape/type validation, `authorize()` delegating to Policy | Do DB traversal |
| **Policy** | Answer "may this user do X to this record" | Mutate anything |
| **Visibility service** | Decide *which fields* of a record a viewer may see | Be bypassable |
| **Action** (single-purpose class) | One atomic domain operation, in a transaction | Know about HTTP |
| **Service** (stateful/collaborating) | Traversal, matching, statistics, sync | Return HTTP responses |
| **Model + Observer** | Persistence, casts, relations, revision capture | Contain authorization |
| **API Resource** | Serialisation, applying the visibility mask | Query the database |
| **Job** | Async/expensive work, idempotent, retryable | Be required for correctness of the response |

**Actions vs Services** — an *Action* is a verb executed once (`AddChildToUnion`,
`ApproveChangeRequest`, `MergePeople`). A *Service* is a capability queried many times
(`TreeTraversalService`, `PersonVisibilityResolver`). We do not create a repository
layer over Eloquent; Eloquent already is one. Repositories appear only in Flutter,
where they genuinely mediate two data sources (API + SQLite).

## 3. Request lifecycle for the hot path (`GET /api/v1/tree/{person}`)

```
1.  Sanctum resolves the token → User
2.  Throttle middleware (tree bucket: 120/min)
3.  ResolveViewerScope middleware
       → loads/caches ViewerScope {tribe_ids, clan_ids, branch_ids, kin_ids, roles}
       → Redis key viewer:scope:{user_id}, TTL 10 min, busted on membership change
4.  TreeController → TreeRequest validates ancestors ≤ 8, descendants ≤ 8, budget ≤ 800
5.  Policy: PersonPolicy@view($user, $person)
6.  Cache lookup: tree:{person_ulid}:{a}:{d}:{scope_hash}:{graph_version}
       hit  → return (ETag + 304 support)
       miss → TreeTraversalService:
                 a) recursive CTE upward over family_edges  (≤ ancestors levels)
                 b) recursive CTE downward over family_edges (≤ descendants levels)
                 c) collect union ids for all touched people
                 d) single hydration query per entity type (no N+1)
                 e) apply PersonVisibilityResolver mask per node
7.  TreeResource serialises {people, unions, edges, meta}
8.  Store in Redis, tagged with each touched person's tag; TTL 1h
```

`graph_version` is a per-tribe integer bumped whenever a relationship/union in that
tribe changes. It makes cache invalidation O(1) instead of hunting down every cached
subtree that contained a changed node.

## 4. Caching strategy

| Cache | Key | TTL | Invalidated by |
|---|---|---|---|
| Viewer scope | `viewer:scope:{user}` | 10 min | membership/role change event |
| Tree subgraph | `tree:{ulid}:{a}:{d}:{scope}:{gv}` | 1 h | `graph_version` bump (implicit) |
| Person card | `person:card:{ulid}:{scope}` | 1 h | `PersonUpdated` event |
| Statistics | `stats:{scope_type}:{id}` | 6 h | nightly rollup job + on-write bump |
| Lineage depth | `lineage:{root}:{person}` | persisted table, not Redis | recompute job |
| Search facets | `facets:tribe:{id}` | 12 h | scheduler |

Rule: **caching is never load-bearing for correctness.** A cold Redis must produce
identical (only slower) results. Privacy is re-evaluated per request via `scope_hash`
in the key, so a cached tree can never leak across permission boundaries.

## 5. Queues

Connection: Redis. Queues by priority:

| Queue | Work | Why async |
|---|---|---|
| `default` | notifications, mail, FCM pushes | latency tolerant |
| `graph` | `RebuildFamilyEdges`, `RecomputeLineageDepth`, `BumpGraphVersion` | can fan out over thousands of rows |
| `media` | thumbnail/derivative generation, checksum, EXIF strip | CPU/IO heavy |
| `matching` | `ScorePersonDuplicates`, match-key regeneration | O(n) over blocks |
| `stats` | tribe/clan/branch rollups | expensive aggregates |

Scheduler:
- hourly — warm tree cache for top-N most-viewed roots
- nightly — statistics rollup, duplicate scan over people changed in last 24h
- weekly — living-status recheck (birth_year older than `living_max_age`), orphan media sweep
- monthly — full match-key rebuild (safe to skip; idempotent)

## 6. Storage

S3-compatible via Laravel `Storage` (`FILESYSTEM_DISK`), targeting **Cloudflare R2** in
production and MinIO/local in dev — no code differences, only `.env`.

- Two disks: `media_public` (profile photos of public/deceased people, tribe logos) and
  `media_private` (documents, audio, anything on a living or restricted person).
- Private files are **never** served directly. `GET /api/v1/media/{ulid}` runs the policy
  and returns a temporary signed URL (5 min) or streams it.
- Path convention `{scope}/{yyyy}/{mm}/{ulid}.{ext}` — no user-supplied filenames on disk.
- Original filename, mime, size, sha256 checksum and dimensions live in `media`.
- Checksum enables both dedupe and tamper detection of archival scans.

## 7. Scaling plan (and the trigger for each step)

| People | Architecture | Trigger to move on |
|---|---|---|
| 0–250k | Single MySQL, Redis, 2 queue workers | — |
| 250k–1M | + read replica for traversal/search, `family_edges` covering indexes | p95 tree > 400ms |
| 1M–5M | + Meilisearch via Laravel Scout for name search; partition `revisions` by year | search p95 > 300ms, revisions > 50M rows |
| 5M+ | Consider materialised ancestor closure **per apical ancestor only**, or a graph store as a *read replica* of MySQL | traversal cost dominates |

Explicitly rejected now:
- **Full closure table** — descendants of a tribal founder across 1M people would produce
  hundreds of millions of rows and rewrite huge swathes on every insert. We store closure
  only for a bounded set of designated apical ancestors (§03).
- **Nested sets / materialised path on people** — genealogy is a DAG with multiple parents,
  not a tree. Both models are wrong for it.
- **Neo4j in v1** — a second source of truth, second consistency problem, second ops burden,
  for a workload that recursive CTEs handle at this scale.

## 8. Observability

- Structured JSON logs; every request carries `request_id`, `user_id`, `scope_hash`.
- Slow-query log at 200ms; a dedicated metric for traversal node count per request.
- `audit_logs` for security-relevant actions (login, role change, merge, hard delete,
  privacy change, claim approval) — separate from `revisions`, which is genealogical.
- Sentry (or equivalent) for exceptions; queue failure alerts on `failed_jobs` growth.

## 9. Environments & configuration

Everything environment-specific is `.env`-driven. Nothing genealogical is hardcoded.

```
APP_URL, APP_KEY
DB_* (MySQL 8.4+ — recursive CTE and window functions required)
REDIS_*
FILESYSTEM_DISK=r2 | s3 | local
R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET, R2_ENDPOINT, R2_URL
SANCTUM_STATEFUL_DOMAINS, SESSION_DOMAIN
QUEUE_CONNECTION=redis
SCOUT_DRIVER=database|meilisearch    MEILISEARCH_HOST, MEILISEARCH_KEY
FCM_SERVER_KEY / FIREBASE_CREDENTIALS
GENEALOGY_LIVING_MAX_AGE=110
GENEALOGY_TREE_MAX_DEPTH=8
GENEALOGY_TREE_MAX_NODES=800
GENEALOGY_DUPLICATE_THRESHOLD=0.82
```

Domain tunables live in `config/genealogy.php` reading from `.env`, so depth caps,
privacy defaults and matching thresholds are operational settings — not code changes.
