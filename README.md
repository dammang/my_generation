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
| 4 — Sanctum auth, API envelope, ViewerScope, policies | ✅ Complete |
| 5 — Tribe / clan / family branch / place APIs, scoped roles | ✅ Complete |
| 6 — People, names, relationships, unions, Add Relative | ✅ Complete |
| 7 — Tree API: traversal, lineage, caching, statistics | ✅ Complete |
| 8 — Filament admin, verification queue, merge UI | ✅ Complete |
| 9 — Flutter foundation: theme, routing, Dio, Drift, Riverpod | ✅ Complete |
| 10 — Flutter auth and onboarding | ✅ Complete |
| 11 — Flutter tree: layout engine, canvas, expand, Go to Me | ✅ Complete |
| 12 — Person profile, family tabs, timeline, Add Relative flow | ✅ Complete |
| 13 — Contribution and verification UI | ✅ Complete |
| 14 — Offline sync | ✅ Complete |
| 15 — Testing, optimisation, hardening | ✅ Complete |

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

## Mobile client

Flutter app in [`mobile/`](mobile/README.md) — see its README for running and
testing. It builds for iOS and Android and talks to this API through one Dio
client that unwraps the response envelope.

The client renders what the server decided: `redacted` and `placeholder` arrive
already applied, dates are shown in the source's own wording ("abt. 1902"), and
`warnings[]` is surfaced without treating the write as failed. The token lives in
the platform keystore; the local Drift cache holds only what the viewer has
already been shown, and is wiped on sign-out.

## Firebase

Firebase is the **identity provider** and the transport for push. It is not the
data store and not the file store — records stay in MySQL, media stay on R2.

The app signs in with Google, Apple or a password through Firebase, then
exchanges the ID token at `POST /api/v1/auth/firebase` for a Sanctum token.
Everything after that point is the API this project already had: same policies,
same scopes, same privacy predicate. Firebase answers *who you are*; Sanctum
answers *what you may do*, and can be revoked, which a Firebase ID token cannot.

Bundle identifier, both platforms: **`com.khanggui`**
(iOS test target: `com.khanggui.RunnerTests`).

What has to be done in the consoles, none of which lives in this repository:

| Step | Where |
|---|---|
| Create the project; add iOS and Android apps under `com.khanggui` | Firebase console |
| Enable Google, Apple and Email/Password sign-in | Firebase → Authentication |
| `google-services.json` → `mobile/android/app/` | Firebase → project settings |
| `GoogleService-Info.plist` → `mobile/ios/Runner/` | Firebase → project settings |
| Service account JSON, stored **outside** the repo; set `FIREBASE_CREDENTIALS` | Firebase → service accounts |
| APNs auth key uploaded, or iOS push silently never arrives | Apple Developer → Keys |
| Sign in with Apple: a Services ID, a key, and the Xcode capability | Apple Developer |
| `khanggui.com` added as an authorised domain | Firebase → Authentication → Settings |
| The **release** signing SHA-1 added, alongside the debug one | Firebase → project settings → Android app |

### The release signing key

`android/app/build.gradle.kts` signs a release with `android/key.properties`
when that file exists, and otherwise falls back to the debug key while printing
a warning. The fallback is there so a fresh clone can still run
`flutter run --release`; the warning is there because a release signed with the
debug key installs perfectly and then fails at the sign-in button, which is a
miserable thing to discover from a user.

Creating the keystore is a person's job, not a script's: it is the app's
permanent identity on Google Play, the password is not recoverable, and losing
the file means never being able to update the app under that identity again.
Back it up somewhere that is not this machine.

```sh
keytool -genkey -v -keystore ~/khanggui-release.jks \
  -keyalg RSA -keysize 2048 -validity 10000 -alias khanggui
```

Then `mobile/android/key.properties`, which is gitignored:

```properties
storeFile=/Users/you/khanggui-release.jks
storePassword=…
keyAlias=khanggui
keyPassword=…
```

Its SHA-1 goes in the Firebase console next to the debug one — both are needed,
or sign-in works in development and fails in production, or the reverse:

```sh
keytool -list -v -keystore ~/khanggui-release.jks -alias khanggui | grep SHA
```

Adding a fingerprint changes `google-services.json`, so download it again
afterwards. `flutter build appbundle` then reports the release config rather
than the warning.

All three credential files are gitignored. The service account JSON in
particular is full administrative access to the Firebase project — it can mint a
token for any user — so it belongs nowhere near a commit.

Without `FIREBASE_CREDENTIALS` set, sign-in and push are simply unavailable; the
rest of the API is unaffected, and a notification still lands in the database
where the app will show it.

## Admin panel

Filament at `/admin`. Access needs an administrative or verifying role — membership alone
is not standing to see the verification queue, merging or role assignment. Every resource
runs the same policies as the API; the panel is not a privacy bypass.

Two screens carry real work:

**Verification queue.** Pending proposals with the diff rendered inline — a reviewer
decides on evidence, not on a record id. Approving re-checks permission at apply time and
compares the record against the snapshot taken when the proposal was filed; one that moved
in between is marked superseded and the reviewer sees the conflict.

**Possible duplicates.** Scored candidates with the reasoning in plain English
("same phonetic name · birth years agree · same birthplace"), because `0.91` is not
evidence. The merge modal offers a choice only where the two records actually disagree.
Merges are reversible: every repointed foreign key is logged and the loser survives as a
tombstone so old links still resolve.

```bash
php artisan genealogy:scan-duplicates --rebuild-keys
```

Duplicate detection blocks on shared match keys before scoring, so it is O(n·k) rather
than O(n²). Scores are normalised by the evidence actually available — the commonest real
duplicate is a record a second contributor added with no relatives attached yet, and
scoring it against the full weight set would mean it never clears the threshold however
exactly the name and dates agree.

## Measured, not assumed

At **101,415 people and 202,769 edges**, over HTTP with auth, privacy and
serialisation included:

| | |
|---|---|
| Tree, 4 up / 3 down | 14.3 ms |
| Tree, 8 up / 6 down (400 nodes) | 16.4 ms |
| People list page | 15.7 ms |
| Person with family | 25.6 ms |
| Search | 32.2 ms |

Every one is bounded by the size of its *answer* rather than the archive:
depth-limited BFS, index seeks with a limit, a node budget on the tree. The
listing was **107 ms** until a composite index put the filter columns ahead of
the sort column — then 0.3 ms, because MySQL could seek instead of scanning and
filtering. Details and the full security review in
[docs/09](docs/09-security-and-scale.md).

## Offline is a first-class state, not a failure

The device stores a **graph**, not response snapshots. Caching whole responses
would mean only the exact trees somebody had already opened could be reopened;
storing people and edges lets any cached person become a focus, which is what
somebody on a plane actually wants. Only what the server already chose to show
that viewer is stored, in the masked form it sent — going offline can never
widen what somebody can read.

A retried write is harmless because the client mints two identifiers itself:

| | |
|---|---|
| **Operation id** (uuid) | The server's ledger is keyed on it. Claiming the key *before* the work runs makes the unique index the lock, so two racing retries cannot both execute |
| **Person ulid** | A person created offline is referable before the server has ever seen them, so an event about a grandfather added on a plane can name him — and there is no id-mapping table |

The batch endpoint is **typed**, not a request forwarder. Re-dispatching
arbitrary paths through the router would create a second way into every endpoint
with its own chances to get authorization wrong.

Four states that must never be confused, because each needs different words:

| State | What it means |
|---|---|
| Empty | Nothing recorded — invites a contribution |
| Withheld | Hidden by privacy — must not invite one |
| Not on device | The phone has no copy — a fact about the device, not the family |
| Rejected | The server refused a queued write — kept, never silently discarded |

## Verification is a gate, not a badge

Marking a record verified locks it against direct edits. From that point anyone
without verify permission in that scope **proposes** rather than overwrites, and
the same `PATCH` either applies or returns `202` with a change request. The
client must not guess which: telling somebody "saved" when the change is waiting
for review is the one thing that screen must never do, because they stop
watching for the answer.

Concurrency is handled without a lock across a human decision. The proposal
carries a snapshot of the record as it was when filed; at approval time that is
compared against the record now. One that moved is marked superseded and the
reviewer gets the three-way diff — never a silent overwrite of whatever somebody
else corrected in between.

## Disagreement is a first-class act

Opening a dispute records a competing value beside the existing one. Nothing is
deleted: both the 1921 and the 1923 survive as claims, and settling records which
was accepted and why. In a family archive the fact that a question was once open
is itself worth keeping — and the answer can turn out to be wrong later.

## Refusals that lead somewhere

A server that refuses a write owes the client enough to fix it. When a child is
added to somebody with two marriages, the server will not guess which one — but
it returns the unions as **data**, not as a sentence with the ids embedded:

```json
{ "success": false, "code": "UNION_AMBIGUOUS",
  "meta": { "choices": [ { "ulid": "01J…", "label": "Marriage to Ngun Hlei" } ] } }
```

The app turns that into a question with two radio buttons. The alternative —
parsing an id back out of `"Marriage to Ngun Hlei (01J…)"` — is not error
handling, it is scraping your own API.

## The chart

The client's layout engine is a **pure function** of the graph — no widgets, no
state, no framework — because layout is the part most likely to be wrong in a way
nobody notices: a sibling drawn under the wrong couple still looks like a family
tree. Being pure is what lets the contract tests lay out a real server response
and assert that no two cards overlap.

What the chart draws is meaning, not decoration:

| | |
|---|---|
| No partner bar for a single parent | There is nobody to join them to; a bar would imply a partner nobody recorded |
| No sibling bar for an only child | A zero-width bar is a smudge, not information |
| Dashed drops for adoptive and step links | The chart must not silently assert biology |
| Masked people still occupy their position | Hiding the node would misrepresent everyone else's lineage |
| The legend says when a tree was truncated | A chart that quietly stops looks like a family that ends there |

## Traversal, and why it is not a recursive CTE

`GET /api/v1/tree/{person}?ancestors=3&descendants=2` returns a depth-limited subgraph:
people with signed `depth` (negative up, positive down), unions with children in birth
order, and edges marked `dashed` for adoptive and step relationships.

Traversal uses **node-level BFS**, not a recursive CTE with a path guard. A CTE
enumerates every distinct *path*, and genealogy is a DAG — two parents each, lines
re-converging whenever cousins marry — so the path count grows exponentially with depth
while the number of people does not. Measured on 100k people:

| | before | after |
|---|---|---|
| default 3 up / 2 down (p95, cold) | 48 ms | **43 ms** |
| max 8 up / 4 down (p95, cold) | 890 ms | **282 ms** |
| lineage recompute over 100k | filled the disk | **3.7 s** |

The depth-64 lineage job was the extreme case: it grew InnoDB's temporary tablespace to
11 GB before failing. `GraphWalker` visits each node once instead.

Everything is capped twice — by depth and by a node budget — and truncation drops the
furthest generations first. Exceeding a cap is a `422` stating the limit, never a silent
clamp that leaves the client believing it received everything.

```bash
php artisan genealogy:recompute-lineage    # generational depth from apical ancestors
php artisan genealogy:seed-scale --people=100000   # local/testing only
```

## Adding a relative

`POST /api/v1/people/{person}/relatives` takes a relationship label and does the rest.
The contributor never learns that a union row exists:

| Relation | What actually gets written |
|---|---|
| `father` / `mother` | Person; parent edge; **joins the child's existing union** if it has a free slot, else creates one |
| `spouse` | Person; union, pair normalised, `order_index` assigned |
| `son` / `daughter` | Person; parent edge for **each** partner; `union_children` row with birth order |
| `brother` / `sister` | Person attached to the same parents — never a sibling row, unless the parents genuinely are not known |
| `guardian` / `other` | A directed relationship of that type |

All of it in one transaction. Ambiguity is refused rather than guessed: adding a child to
someone with two marriages returns `422 UNION_AMBIGUOUS` listing the choices.

## Two kinds of rule

**Cycles are a hard error.** They make every traversal below them incorrect, not merely
doubtful. The response names the offending path:

> This relationship would create a loop: Thawng Dam → Hau Neng → Tun Khoi → Thawng Dam.

**Everything else is a warning**, returned in `warnings[]` alongside a successful write.
A child born 20 years after the father's death is recorded *and* flagged — refusing it
would lose the record, and the transcription is more often wrong than the family.

```json
{ "success": true, "data": { … },
  "warnings": [{ "code": "CHILD_BORN_AFTER_PARENT_DEATH",
                 "message": "Born 1960, 20 years after Za Kam's recorded death. Please verify.",
                 "field": "birth_date" }] }
```

Thresholds live in `config/genealogy.php`, because what counts as implausible differs by
era and community.

## Claiming a profile

A genealogy record usually exists before its subject ever opens the app — an
uncle added them years ago. `POST /api/v1/profile-claims` is how an account asks
to be recognised as one, and it is deliberately a **request**:

- Approving also makes the claimant close kin of everybody around that person,
  so it widens what they can see across a family.
- A deceased person cannot be claimed, nor one somebody is already verified as,
  nor one the claimant cannot already see — claiming must not become a way to
  discover who exists.
- Nobody may decide their own claim, or a family admin could quietly claim any
  living relative.
- Eligibility is re-checked at approval time, not submission: somebody else may
  have been verified as that person while the claim sat in the queue.
- `users.person_id` is written in exactly one place, and every approval is
  audited.

## Belonging vs capability

Two separate things, deliberately kept apart:

| | Table | Grants |
|---|---|---|
| **Membership** | `memberships` | Visibility of records scoped to that tribe, clan or branch. Nothing else. |
| **Role** | `scope_role_user` | Permission to act, within one scope and everything beneath it. |

A pending membership grants nothing at all. Approving one busts the applicant's cached
entitlements immediately rather than at the next TTL expiry.

Role assignment carries two guards, both in `AssignScopedRole` so they apply to Filament
and any future caller as well as the API:

1. The granter must hold `roles.assign` at the target scope.
2. The granter may not grant a permission they do not themselves hold there — otherwise a
   family admin with `roles.assign` could mint a tribe admin and escape their own scope in
   one call.

`super-admin` is not grantable from a scoped endpoint at all.

## How privacy is enforced

The API decides what a requester may see; the client is a renderer. Two separate
questions, answered in two places that must agree:

| Question | Answered by | Why separate |
|---|---|---|
| May this record be seen at all? | `Person::scopeVisibleTo()` — a SQL predicate | Filtering after pagination gives short pages and leaks counts |
| Which of its fields survive? | `PersonVisibilityResolver` → `FieldMask` → `PersonResource` | One place decides field visibility, for every serialisation path |

`ViewerScope` holds the requester's entitlements — memberships, administered scopes,
close kin, permissions — resolved once per request and cached as primitives. Its `hash()`
goes into every cache key that can hold person data, so a cached payload can never be
served across a permission boundary.

Rules worth knowing before you touch this:

- A record the requester may not see returns **404, not 403**. A 403 confirms existence.
- A person with no dates is treated as **living**. Fail closed.
- A living minor is never visible outside the family scope, whatever their privacy level.
- A masked person still renders as a node in a tree — the content is withheld, the shape
  of the graph is not, or everyone's lineage would be misrepresented.

Authority flows downward by prefix-matching `scopes.path`, so a Tribe Admin needs no row
per clan:

```php
$permissions->can($user, 'people.verify', $scopePath);   // '/1/14/57/'
```

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

## Deploying

Forge onto your own server: site `khanggui.com`, **PHP 8.4**. Nothing here is
Forge-specific except where it says so; the parts that bite are the same
anywhere.

`composer.json` pins `config.platform.php` to **8.4.1** so this machine resolves
dependencies against the version the server runs, rather than the 8.5 this
machine happens to have. Without the pin, `composer update` here can lock a
package that will not install there, and the first sign of it is a failed
deploy.
`composer check-platform-reqs --no-dev` reports every locked package satisfied,
and no application code uses anything newer.

8.4.**1**, not 8.4.0: `symfony/console` in the current lock requires >= 8.4.1,
so a server on 8.4.0 exactly would fail at `composer install` with a message
about the lock rather than about PHP. Worth confirming with `php -v` on the
server — any current Forge 8.4 is well past it.

The site's PHP needs these extensions — Forge installs most, but `intl`, `zip`
and `sodium` are worth confirming:

```
ctype dom fileinfo filter hash iconv intl json libxml
mbstring openssl pcre session sodium tokenizer xmlreader zip
```

**The deploy script.** Two lines matter that a stock Laravel script does not
have: the asset build, because the password-reset and verification pages are
served by this app and `public/build` is gitignored, and the queue restart,
because a running worker holds the old code until it is told otherwise.

```sh
cd /home/forge/khanggui.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# The reset-password and verify-email pages need built assets, or every visit
# throws ViteException: Unable to locate file in Vite manifest.
npm ci
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

$FORGE_PHP artisan migrate --force

$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan event:cache

# The worker is running the previous release until this.
$FORGE_PHP artisan queue:restart
```

**Two processes, not just the site.** Neither is optional:

| What | Why | Where |
|---|---|---|
| Queue worker on the `database` connection | Review notifications are queued; without a worker nobody is ever told their change needs approval | Forge → Queue |
| Scheduler (`php artisan schedule:run` every minute) | Prunes the idempotency ledger; without it the table grows forever | Forge → Scheduler |

**Environment.** Beyond the usual `APP_KEY`, database and `APP_ENV=production`
with `APP_DEBUG=false`:

| Variable | Note |
|---|---|
| `APP_URL=https://khanggui.com` | Verification links are **signed against this host**. If it does not match what people actually reach, every link 403s with "Invalid signature" |
| `FIREBASE_CREDENTIALS` | Absolute path to the service account JSON, uploaded outside the site directory so a deploy cannot overwrite it and the web root cannot serve it |
| `MAIL_*` | A real mailer. `log` is the default and silently sends nothing |
| `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` | Read via `config/super_admin.php`, not `env()` directly — a cached config stops answering `env()` at all, which is why the seeder used to report a password missing that was sitting in `.env` |

Then, once only:

```sh
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=SuperAdminSeeder
```

Both are safe to re-run; the admin seeder updates rather than duplicating.

**Before the first deploy**, in the consoles: `khanggui.com` registered with
Apple for Sign in with Apple email relay (or mail to Apple users silently
vanishes), and SPF covering whatever actually sends.
