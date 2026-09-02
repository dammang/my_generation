# My Generation — Architecture Documentation

Collaborative genealogy, tribal heritage and family-chronicle platform.

| Doc | Contents |
|---|---|
| [01-system-architecture.md](01-system-architecture.md) | Components, request lifecycle, caching, queues, storage, scaling plan |
| [02-database-architecture.md](02-database-architecture.md) | ERD, full table catalogue, columns, types, FKs, indexes, constraints |
| [03-genealogy-model.md](03-genealogy-model.md) | Relationship/union model, traversal engine, generation calculation |
| [04-privacy-model.md](04-privacy-model.md) | Visibility scopes, living-person rules, field masking, policies |
| [05-verification-model.md](05-verification-model.md) | Change requests, revisions, disputes, duplicates, merges |
| [06-api-architecture.md](06-api-architecture.md) | Versioning, envelope, auth, endpoints, tree API contract, offline sync |
| [07-project-structure.md](07-project-structure.md) | Laravel folder layout, Flutter folder layout |
| [08-roadmap.md](08-roadmap.md) | Phase-by-phase MVP plan and what each phase delivers |

## The one-paragraph version

People and the facts that connect them are stored as a **graph**, never as a tree.
`people` are nodes. `relationships` (parent→child, guardian, asserted-sibling) and
`unions` (marriages/partnerships) are edges. Everything a user can see is decided
**server-side** from their membership in tribes, clans and family branches. Every
genealogical fact carries a verification status, an author, a revision trail and
optional source citations, so the database can hold *disagreement* without losing
history. The visual family tree is a **projection** computed on demand from a
depth-limited traversal of that graph — it is never stored.

## Non-negotiable invariants

1. The database is the source of truth. No pre-rendered tree is ever persisted as truth.
2. No genealogical fact is destroyed. Corrections create revisions; deletions are soft.
3. The API never returns data the requester is not entitled to. Clients hide nothing that matters.
4. No endpoint may return an unbounded graph. Depth **and** node budget are always capped.
5. A `user` is not a `person`. They are linked only through a verified profile claim.
6. Every write that touches more than one row runs inside a transaction.
