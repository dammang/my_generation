# 04 — Privacy Model

**Principle:** the API decides what a requester may see. The client is a renderer, not a
gatekeeper. If Flutter ever hides a field, the server has already failed.

## 1. Visibility levels

| Level | Who can see the full record |
|---|---|
| `public` | Anyone, including unauthenticated share-link visitors |
| `tribe` | Active members of the person's tribe |
| `clan` | Active members of the person's clan (or any ancestor clan admin) |
| `family` | Members of the person's family branch, plus close kin (§4) |
| `private` | Only the record's contributors, the linked user, and scope admins |

Default on a new person: `family`.

**A recorded death lifts the level.** A living person's own choice governs; once
`is_living` is false the record answers to `tribes.default_privacy_level` instead. A
genealogy is read generations after it is written, and a permanent lock would fill the
tree with nodes nobody could ever read. Lifting never *tightens*: somebody who chose
`public` stays public, so it relaxes a restriction and never imposes one.

This rule lives in two places that must agree — `PersonVisibilityResolver::levelFor`
and `Person::applyDeathLift` (the SQL half). A disagreement means a person listed by the
query and then refused by the policy, or worse, the other way round.

A person may set their own level through `PATCH /people/{person}/visibility`, which needs
no `people.update` permission and never becomes a change request: nobody should wait in a
reviewer's queue to stop being visible. The app offers four of the five levels — `tribe`
is omitted as a rung most people cannot tell apart from `clan`.

## 2. Living vs deceased

A person is treated as **living** (strictest handling) unless the server can prove
otherwise:

```
deceased  ⇔  death_date IS NOT NULL
          OR death_year IS NOT NULL
          OR (birth_year IS NOT NULL AND birth_year < current_year - GENEALOGY_LIVING_MAX_AGE)
```

`is_living` is a maintained convenience flag; the resolver recomputes from the facts.
A person with no dates at all is treated as living — **fail closed**.

For living people, regardless of `privacy_level`, the following are withheld from anyone
outside the family scope:
- exact `birth_date` (year only, or nothing if `privacy_level` ≥ `family`)
- `birth_place` below district granularity
- biography, all `person_events`, all media in private collections
- any linked user's email/phone

Minors (birth_year within the last 18 years) are hardest-locked: name and relationship
position only, visible to family scope only, never in public search, never in a share link.

## 3. The two-stage mechanism

**Stage 1 — Policy (may you see this record at all?)**

`PersonPolicy@view` returns bool. Used by controllers, and pushed into queries as a
scope so listings and search never leak existence:

```php
Person::visibleTo($user)   // adds the privacy predicate to the query, not a post-filter
```

Post-filtering a paginated list is a bug: it produces short pages and leaks counts.
The predicate goes in the `WHERE`.

**Stage 2 — Field mask (which fields of it?)**

`PersonVisibilityResolver::mask(User $viewer, Person $person): FieldMask` returns the
permitted field set. `PersonResource` renders through the mask. There is exactly one
place in the codebase that decides person field visibility, and every serialisation path
— API, search results, tree nodes, share links, exports, notifications — goes through it.

```php
// PersonResource::toArray()
$mask = $this->visibilityMask();          // resolved once per request, memoised
return array_filter([
    'ulid'         => $this->ulid,
    'display_name' => $mask->name ? $this->display_name : $this->maskedName(),
    'birth'        => $mask->birthDate ? $this->birthFact() : $this->birthYearOnly($mask),
    'biography'    => $mask->biography ? $this->biography : null,
    …
]);
```

A masked living person in someone else's tree still renders as a card — name (or
"Private"), gender, and their structural position — because otherwise the tree breaks.
What is withheld is the *content*, never the *shape* of the graph… except for `private`
people, who are replaced by an opaque placeholder node with no name.

## 4. ViewerScope

Resolved once per request by middleware, cached 10 minutes in Redis, busted on
membership/role change:

```php
final class ViewerScope {
    public array $tribeIds;        // active memberships
    public array $clanIds;
    public array $branchIds;
    public array $adminScopePaths; // e.g. ['/1/', '/1/14/'] — prefix-matched
    public array $kinPersonIds;    // close kin of the viewer's claimed person
    public bool  $isSuperAdmin;
    public string $hash;           // stable hash — part of every cache key
}
```

`kinPersonIds` is computed from the viewer's claimed person: everybody within
`privacy.kin_cousin_degree` cousins (default 3), plus their own line down and spouses,
capped at a few hundred ids. Nth cousins are the people descended from an ancestor N+1
generations up, by no more generations than it took to climb to them. This is what makes
"family" a *relational* scope rather than merely a branch label, so an uncle who was
never assigned to the right family branch still sees his nephew.

Everybody in a clan is a cousin at some degree, so this number is what decides whether
"close family" means anything different from "the clan". Measured against the JK clan of
327 people, third cousins reaches 30 for the widest-connected person and 10 for the
narrowest — a real circle, not the whole archive.

An earlier version descended only one generation from the direct line, which reached
every aunt and uncle and not one first cousin, while its own comment said otherwise.

`hash` is appended to every cached tree/person key. A cached payload therefore cannot be
served to a viewer with a different entitlement set.

## 5. Enforcement checklist (every one of these is a test)

- [ ] `GET /people` never lists a person failing `visibleTo`
- [ ] `GET /people/{ulid}` returns 404 (not 403) for records the viewer may not know exist
- [ ] Tree endpoints mask living-person fields at every depth, including root
- [ ] Search results are filtered in SQL, not after pagination
- [ ] Share links cannot exceed their `max_privacy_level` and always mask living people
- [ ] Private media returns a signed URL only after the policy passes
- [ ] Filament admin respects the same policies (Filament is not a privacy bypass)
- [ ] Notifications never quote a field the recipient cannot see
- [ ] GEDCOM/PDF export applies the same mask as the API
- [ ] A cached response cannot be served across `ViewerScope::$hash` boundaries

## 6. Roles & permissions

Global roles via Spatie; scoped roles via `scope_role_user` (§02 §6.3).

| Role | Scope | Core capability |
|---|---|---|
| Super Admin | global | everything; `Gate::before` short-circuit |
| Tribe Admin | tribe | manage clans/branches, verify anything in the tribe, manage members |
| Clan Admin | clan | manage branches, verify within the clan |
| Family Admin | family branch | verify within the branch, approve profile claims |
| Historian / Verifier | any scope | verify facts, resolve disputes, merge duplicates — no member management |
| Contributor | any scope | create + edit unverified records; edits to verified records become change requests |
| Member | any scope | view per privacy rules, comment, save people |
| Viewer | none | public + share-link content only |

Permission names (checked, never inferred):

```
people.view people.create people.update people.delete people.verify people.merge
relationships.create relationships.update relationships.delete relationships.verify
unions.create unions.update unions.verify
events.create events.update events.verify
stories.create stories.update stories.verify
sources.create sources.update sources.verify
media.upload media.delete
tribes.manage clans.manage families.manage generations.manage places.manage
changes.review changes.approve disputes.resolve duplicates.review
users.manage roles.assign claims.approve
```

`PermissionResolver::can($user, 'people.verify', $scopeId)` checks: super-admin →
global role → any scoped role whose `scopes.path` is a **prefix** of the target scope's
path. Prefix matching is why a Tribe Admin automatically has authority in every clan
beneath, with no recursive query at request time.
