# My Generation

Collaborative genealogy, tribal heritage and family-chronicle platform.
Laravel API + Filament admin + Flutter mobile client.

Architecture and database design live in [`docs/`](docs/00-index.md) — read
[`docs/02-database-architecture.md`](docs/02-database-architecture.md) before changing
the schema.

## Current state

| Phase | Status |
|---|---|
| 1 — Architecture & database design | ✅ Complete |
| 2 — Migrations, enums, concerns, factories, seeders | ✅ Complete |
| 3 — Models, relations, observers, revision + edge projection | ✅ Complete |
| 4 — Sanctum auth, API envelope, ViewerScope, policies | Next |

## Requirements

- PHP 8.3+ (developed on 8.5)
- MySQL 8.4+ — recursive CTEs, CHECK constraints and the ngram full-text parser are
  required, not optional. **SQLite is not supported**, including for tests.
- Composer 2
- Redis (optional in development; cache and queue fall back to the database driver)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE my_generation CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
php artisan migrate:fresh --seed
```

The seeder creates roles and permissions, system event types, a starting gazetteer, a
super admin, and — in `local` only — a small demo tribe.

Set `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` in `.env` before seeding in any
non-local environment; the seeder refuses to invent a password there.

## Tests

```bash
php artisan test
```

Tests run against `my_generation_test`, on MySQL:

```bash
mysql -u root -e "CREATE DATABASE my_generation_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
```

## Commands

```bash
php artisan genealogy:rebuild-edges --fresh
```

Regenerates the derived `family_edges` adjacency table from `relationships`. It is a
cache, not truth — safe to run at any time, and the right first move after an import,
a merge, or a suspected inconsistency.

## Configuration

Domain behaviour is `.env`-driven through `config/genealogy.php`: traversal depth caps
and node budgets, living-person inference, privacy defaults, the contribution trust
ramp, duplicate-matching weights and the transliteration ruleset. None of it requires a
code change to tune.

## How the graph maintains itself

Writes to `relationships`, `unions` and `people` trigger observers that keep the derived
state correct **synchronously**, so a contributor who adds a father sees him in the tree
immediately rather than whenever a queue drains:

| Write | Derived effect |
|---|---|
| Relationship created/updated/deleted | `family_edges` projected or retracted; tribe `graph_version` bumped |
| Person saved | `display_name`, `sort_name`, `is_living` derived; scope counters adjusted |
| Union saved | Partner pair normalised to `partner_1_id < partner_2_id`; `order_index` assigned |
| Union child added/removed | `unions.children_count` recomputed; `birth_order` defaulted |
| Tribe/clan/branch saved | `scopes` row created and its materialised `path` maintained |
| Place saved | Materialised `path` and `depth` maintained, descendants re-pathed |

Bulk work should skip all of it and catch up in set-based SQL afterwards:

```php
GraphSideEffects::without(fn () => $importer->run());
Person::withoutRevisions(fn () => $importer->run());
```

then `php artisan genealogy:rebuild-edges --fresh`.

Every change to an audited field is written to `revisions` by a model observer, so no
call site can forget it. Attach context before saving:

```php
$person->withRevisionContext(reason: 'Baptism register, entry 114', sourceId: $source->id);
```

## Conventions

- Internal `id` is `BIGINT`; every client-facing identifier is a `ULID`. **Internal ids
  never appear in an API response.**
- Dates use the four-column uncertain-date pattern — see `HasUncertainDates`.
- Unique keys always include `deleted_token`; see `SoftDeletesWithUniqueness` for why.
- Ledgers (`revisions`, `change_requests`, `audit_logs`, `person_merges`) are
  append-only and never soft-deleted.
- Polymorphic columns store short morph aliases (`person`, `relationship`), enforced in
  `AppServiceProvider`. Renaming a model must not orphan its revisions and citations.
- Lazy loading throws outside production. Eager load, or use an explicit relation query.
- Spouses and siblings are **derived**, never stored. See `Person::spouses()` and
  `Person::siblings()`.
