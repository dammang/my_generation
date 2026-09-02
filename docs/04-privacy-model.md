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

Default on a new person: `family`. Default on a deceased person with a death year more
than 100 years ago: `tribe` (configurable per tribe via `tribes.default_privacy_level`).

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

`kinPersonIds` is computed from the viewer's claimed person: 2 generations up,
2 down, plus spouses and siblings — capped at a few hundred ids. This is what makes
"family" a *relational* scope rather than merely a branch label, so an uncle who was
never assigned to the right family branch still sees his nephew.

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
