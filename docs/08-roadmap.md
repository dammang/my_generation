# 08 — Development Roadmap

Each phase ends with something runnable and tested. Nothing is merged as a TODO.

| Phase | Deliverable | Done when |
|---|---|---|
| **1** | Architecture & database design (this document set) | You approve it |
| **2** ✅ | Migrations, enums, model concerns, factories, seeders | **Done.** 47 migrations / 55 tables / 147 FKs / 3 CHECK constraints / 7 full-text indexes. `migrate:fresh --seed` green; 40 tests passing |
| **3** ✅ | Models, relations, observers, revision + edge projection | **Done.** Relations on all 37 models; 8 observers; live edge projection; field-level revision ledger; morph map; 83 tests passing |
| **4** ✅ | Sanctum auth, envelope, error handling, ViewerScope, policies | **Done.** Auth flows, one response envelope, six throttle buckets, ViewerScope + PermissionResolver + PersonVisibilityResolver, 10 policies, privacy pushed into SQL. 143 tests passing |
| **5** ✅ | Tribe / Clan / Family Branch / Place / Generation API + scoped roles | **Done.** 45 routes; clans nest to any depth with re-parenting that repaths the scope spine; membership requests; role assignment with an escalation guard. 174 tests passing |
| **6** ✅ | People, names, relationships, unions, `AddRelative`, integrity rules | **Done.** 62 routes. Add Father/Mother/Spouse/Son/Brother from one endpoint; cycles rejected with the offending path named; warnings returned alongside successful writes. 204 tests passing |
| **7** ✅ | Tree API: traversal, depth caps, lineage, path-finder, caching, statistics | **Done.** Measured on 100,469 people / 200,918 edges: default depth p95 **43ms** cold, 13ms warm. Query-count budget test passes. 229 tests passing |
| **8** ✅ | Filament admin: resources, dashboard, verification queue, merge UI | **Done.** 14 resources, 2 dashboard widgets, verification queue with inline diffs, reversible merge with a field-by-field comparison. Verified in the browser. 262 tests passing |
| **9** ✅ | Flutter foundation: theme, routing, Dio, Drift, Riverpod, l10n | **Done.** Builds for iOS and Android, runs on the simulator, and `/auth/me` verified by live contract tests against a running Laravel. 12 unit + 6 contract tests |
| **10** ✅ | Flutter auth + onboarding (pick tribe/clan, claim profile) | **Done.** Register, sign in, password reset, join a tribe, claim a profile. Session survives restart. Laravel 274 tests; Flutter 33 + 7 contract tests |
| **11** ✅ | Flutter tree: layout engine, canvas, cards, expand/collapse, Go to Me | **Done.** Rendered on the simulator against a 645-person seeded graph. Layout engine is a pure function with 21 unit tests; contract tests lay out a real response and assert no overlaps |
| **12** ✅ | Flutter person profile, family tabs, timeline, Add Relative flow | **Done.** Verified on the simulator against the seeded graph. Timeline endpoints added server-side; the ambiguous-union refusal is recovered from in the UI rather than reported |
| **13** ✅ | Contribution & verification UI (both sides) + revision history | **Done.** Verified end to end against the server: a contributor's edit to a verified record became a proposal, appeared in the reviewer's queue with its diff, and the record stayed untouched until decided |
| **14** | Offline: Drift mirror, sync queue, idempotent batch, conflict surfacing | Airplane mode: browse cached tree, add a relative, sync on reconnect |
| **15** | Tests, indexes, load testing at 1M rows, caching tuning, hardening | Load test passes; security review checklist clean |

**Version 2** (after MVP ships): stories, oral history + media capture, sources UI,
migration map, advanced duplicate detection, merge from the app, profile claiming flow
polish, GEDCOM import/export, PDF/chart export, QR share, FCM notifications, Meilisearch.

**Version 3**: AI-assisted duplicate suggestion, OCR of scanned records, automatic
transcription and translation of oral histories, relationship discovery, public heritage
archive. **AI output is always a change request — it never writes to verified genealogy.**

## Manual configuration you will need to provide

| When | What |
|---|---|
| Phase 2 | MySQL 8.4+ database + credentials in `.env` (MySQL 9.6 is installed locally — fine) |
| Phase 2 | Redis running (`brew services start redis`) |
| Phase 4 | `APP_KEY`, `SANCTUM_STATEFUL_DOMAINS`, mail driver for password reset (Mailpit locally) |
| Phase 8 | A super-admin account (created by seeder; you set the password) |
| Phase 9 | Xcode + Android SDK licences accepted; the API base URL for the simulator |
| Phase 14 | Cloudflare R2 bucket + keys (or MinIO locally) |
| V2 | Firebase project + `google-services.json` / `GoogleService-Info.plist` for FCM |
| V2 | Meilisearch host + key, if search outgrows MySQL full-text |

## Decisions taken in Phase 2 without you

You asked to start before answering the five questions, so these are the defaults I
built on. Each is cheap to change now and stated here so nothing is silently assumed.

| # | Question | What I built | Reversing it |
|---|---|---|---|
| 1 | First tribe to tune for | Zomi/Tedim. The transliteration ruleset in `config/genealogy.php` and the factory name pool (`App\Support\NameCorpus`) are Tedim-flavoured | Config edit; no migration |
| 2 | Multi-tribe people | **Supported.** `people.tribe_id` stays the primary affiliation on the scoping hot path; a `person_affiliations` pivot records additional tribes/clans. Gated by `GENEALOGY_MULTI_TRIBE` | Drop one table, or leave it unused |
| 3 | Living-person default privacy | `family`, via `GENEALOGY_DEFAULT_PRIVACY` and the `people.privacy_level` column default | `.env` change; a data migration if records already exist |
| 4 | Who routes through review | Trust ramp of 3 approved contributions, `GENEALOGY_TRUST_RAMP`. Set to 0 to disable. Edits to *verified* records always become change requests regardless | `.env` change |
| 5 | Languages at launch | Scaffolding only — enum labels resolve through `__('enums.*')`, so translations are lang files, not code changes. No `.arb`/lang files written yet | Nothing to undo |

I took the multi-tribe pivot because it was the one you flagged as expensive later, and
adding an unused table now costs nothing. Say the word if any of these are wrong.

## Open questions for you

1. **First real tribe** — which tribe/clan should the seed structure and the
   transliteration ruleset be tuned for? (Zomi/Tedim from your examples?)
2. **Who verifies by default** — should a brand-new contributor's first submissions
   always go to a change request, or only edits to verified records?
3. **Living-person default privacy** — `family` (my recommendation) or `clan`?
4. **Multi-tribe people** — can one person belong to two tribes (e.g. mixed marriage
   lineage)? Current schema says one; a pivot table is a small change now, a large one later.
5. **Languages at launch** — English + Burmese + Tedim, or English only in MVP with the
   l10n scaffolding in place?
