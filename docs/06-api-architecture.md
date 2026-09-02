# 06 — API Architecture

Base: `/api/v1`. Auth: Laravel Sanctum personal access tokens (`Authorization: Bearer …`).
Content type: `application/json`. All identifiers in requests and responses are **ULIDs**.

## 1. Response envelope

Success:
```json
{ "success": true, "data": { … }, "meta": { … }, "warnings": [] }
```
Collection:
```json
{ "success": true, "data": [ … ],
  "meta": { "cursor": "eyJpZCI6…", "next_cursor": "…", "per_page": 25, "has_more": true } }
```
Error:
```json
{ "success": false, "message": "You are not authorized to modify this person.",
  "errors": { "birth_date": ["The birth date must be a valid date."] },
  "code": "AUTHORIZATION_FAILED" }
```

Status codes: `200` ok · `201` created · `202` accepted (queued) · `204` no content ·
`400` malformed · `401` unauthenticated · `403` unauthorised · `404` not found *or
not visible* · `409` conflict (superseded change request, merge conflict) ·
`422` validation/domain rule · `429` throttled · `500` server error.

`404` rather than `403` for records the viewer may not know exist — a `403` confirms
existence and leaks the graph.

**`warnings[]`** is the genealogy-specific piece: a successful `201` may carry
`[{ "code":"CHILD_BORN_AFTER_PARENT_DEATH", "message":"Born 3 years after father's recorded death. Please verify.", "field":"birth_date" }]`.
The record was created; the doubt was recorded.

## 2. Pagination

Cursor-based (`meta.next_cursor`) everywhere. Offset pagination on a 1M-row table with a
privacy predicate degrades badly at high offsets, and genealogy lists are naturally
"keep scrolling", not "jump to page 400".

## 3. Rate limits

| Bucket | Limit |
|---|---|
| `auth` (login/register/reset) | 5/min per IP + per email |
| `read` | 300/min per user |
| `tree` | 120/min per user |
| `search` | 60/min per user |
| `write` | 60/min per user |
| `upload` | 20/min per user |

## 4. Endpoint map (MVP)

### Auth
```
POST   /api/v1/auth/register              public
POST   /api/v1/auth/login                 public   → token
POST   /api/v1/auth/logout                auth
POST   /api/v1/auth/forgot-password       public
POST   /api/v1/auth/reset-password        public
GET    /api/v1/auth/me                    auth     → user + claimed person + scopes + permissions
PATCH  /api/v1/auth/profile               auth
POST   /api/v1/auth/devices               auth     register FCM token
```

### Organisation
```
GET    /api/v1/tribes                     ?search=&country=&cursor=
GET    /api/v1/tribes/{ulid}
GET    /api/v1/tribes/{ulid}/clans        ?parent=root|{ulid}
GET    /api/v1/tribes/{ulid}/statistics
GET    /api/v1/clans/{ulid}
GET    /api/v1/clans/{ulid}/branches
GET    /api/v1/family-branches/{ulid}
GET    /api/v1/generations?tribe=&clan=
POST   /api/v1/memberships                request membership in a scope
```
Tribe/clan/branch writes: admin only, `tribes.manage` / `clans.manage` / `families.manage`.

### People
```
GET    /api/v1/people                     ?tribe=&clan=&branch=&q=&living=&cursor=
POST   /api/v1/people
GET    /api/v1/people/{ulid}
PATCH  /api/v1/people/{ulid}
DELETE /api/v1/people/{ulid}              soft delete, people.delete
GET    /api/v1/people/{ulid}/family       parents, spouses, children, siblings — one call
GET    /api/v1/people/{ulid}/timeline     person_events, chronological
GET    /api/v1/people/{ulid}/media
GET    /api/v1/people/{ulid}/sources      citations grouped by field
GET    /api/v1/people/{ulid}/history      revisions
GET    /api/v1/people/{ulid}/names
POST   /api/v1/people/{ulid}/names
POST   /api/v1/people/{ulid}/relatives    ← the Add Relative action (§5)
```

### Tree
```
GET    /api/v1/tree/{ulid}                ?ancestors=3&descendants=2&include=spouses,siblings
GET    /api/v1/tree/{ulid}/ancestors      ?depth=4&cursor=
GET    /api/v1/tree/{ulid}/descendants    ?depth=4&cursor=
GET    /api/v1/tree/{ulid}/lineage        direct line to the branch's apical ancestor
GET    /api/v1/tree/{ulid}/path-to/{other} relationship path between two people
```

### Relationships & unions
```
POST   /api/v1/relationships              PATCH/DELETE /api/v1/relationships/{ulid}
POST   /api/v1/unions                     PATCH/DELETE /api/v1/unions/{ulid}
POST   /api/v1/unions/{ulid}/children     { person, relationship_type, birth_order }
DELETE /api/v1/unions/{ulid}/children/{personUlid}
```

### Chronicle, stories, sources, media
```
GET|POST   /api/v1/person-events          PATCH|DELETE /api/v1/person-events/{ulid}
GET|POST   /api/v1/stories                GET|PATCH|DELETE /api/v1/stories/{ulid}
GET|POST   /api/v1/sources                GET|PATCH /api/v1/sources/{ulid}
POST       /api/v1/citations              DELETE /api/v1/citations/{id}
POST       /api/v1/media                  multipart; returns 202 while conversions queue
GET        /api/v1/media/{ulid}           policy-checked signed URL / stream
```

### Search & places
```
GET    /api/v1/search?q=&type=people|stories|places|all&tribe=&clan=&birth_year_from=&birth_year_to=
GET    /api/v1/places?q=&parent=&type=
POST   /api/v1/places
```

### Collaboration
```
GET    /api/v1/change-requests            ?status=pending&scope=   (verifier queue)
POST   /api/v1/change-requests
GET    /api/v1/change-requests/{ulid}
POST   /api/v1/change-requests/{ulid}/approve | /reject | /request-info | /withdraw
GET|POST /api/v1/disputes                 POST /api/v1/disputes/{ulid}/claims | /resolve
GET    /api/v1/duplicates                 POST /api/v1/duplicates/{ulid}/merge | /keep-separate
POST   /api/v1/profile-claims             POST /api/v1/profile-claims/{ulid}/approve|reject
GET    /api/v1/me/contributions
GET    /api/v1/notifications              POST /api/v1/notifications/{id}/read
```

### Sync & sharing
```
POST   /api/v1/sync/batch                 queued offline operations, idempotent
GET    /api/v1/sync/changes?since=        delta pull for the local SQLite mirror
POST   /api/v1/share-links                DELETE /api/v1/share-links/{ulid}
GET    /api/v1/public/share/{token}       unauthenticated, privacy-capped
```

## 5. Two contracts documented in full

### `GET /api/v1/tree/{person}`

**Auth** Bearer token. **Permission** `PersonPolicy@view` on the focus person.

| Param | Type | Default | Max | Notes |
|---|---|---|---|---|
| `ancestors` | int | 3 | 8 | generations upward |
| `descendants` | int | 2 | 8 | generations downward |
| `include` | csv | `spouses` | — | `spouses,siblings,events` |
| `budget` | int | 400 | 800 | max nodes returned |

**200**
```json
{ "success": true,
  "data": {
    "focus": "01HZXK2M…",
    "people": [
      { "ulid":"01HZXK2M…", "depth":0, "display_name":"Thawng Dam",
        "native_name":"ထန်ဒမ်", "gender":"male",
        "birth": { "year":1920, "display":"1920", "precision":"year" },
        "death": { "year":1998, "display":"1998", "precision":"year" },
        "is_living": false, "photo_url":"https://…/thumb.webp",
        "verification_status":"verified", "has_open_dispute": false,
        "tribe":"Zomi", "clan":"Guite", "generation_label":"13th Generation",
        "privacy":"public", "masked": false }
    ],
    "unions": [
      { "ulid":"01HZY…", "partners":["01HZXK2M…","01HZXQ…"],
        "children":["01HZZ1…","01HZZ2…"], "union_type":"marriage",
        "marriage_year":1948, "status":"widowed", "order_index":1 }
    ],
    "edges": [ { "parent":"01HZXK2M…", "child":"01HZZ1…", "kind":"biological" } ]
  },
  "meta": { "ancestors_depth":3, "descendants_depth":2, "node_count":214,
            "truncated":false, "graph_version":4127,
            "expandable": { "01HZZ1…": { "children":12, "parents":0 } } } }
```

**Errors** `404` person not found or not visible · `422` depth above cap ·
`429` throttled. Responses carry an `ETag`; a matching `If-None-Match` returns `304`.

### `POST /api/v1/people/{person}/relatives`

**Auth** Bearer. **Permission** `people.create` in the person's scope.

```json
{ "relation": "son",
  "person": { "first_name":"John", "last_name":"Dam", "native_name":"ဂျွန်",
              "gender":"male", "birth_date":"1975", "birth_date_precision":"year",
              "birth_place_ulid":"01HP…" },
  "union_ulid": "01HZY…",
  "relationship_subtype": "biological",
  "source_ulid": "01HS…",
  "client_operation_id": "8f14e45f-…" }
```

`relation` ∈ `father, mother, spouse, son, daughter, brother, sister, guardian, other`.
The server derives every row (§03 §6). If `relation` is a child and the person has more
than one union and `union_ulid` is absent → `422 UNION_AMBIGUOUS` listing the choices.

**201**
```json
{ "success": true,
  "data": { "person": {…}, "created": { "people":1,"relationships":2,"union_children":1 },
            "change_request": null },
  "warnings": [ { "code":"PARENT_AGE_LOW",
                  "message":"Father would have been 14. Please verify.",
                  "field":"birth_date" } ] }
```

If the contributor lacks direct-write rights, the response is **202** with
`data.change_request` populated and `data.person` null.

## 6. Offline sync contract

The Flutter client generates a ULID for every locally created record and a UUID
`client_operation_id` for every mutation, queued in SQLite.

```
POST /api/v1/sync/batch
{ "operations": [
    { "client_operation_id":"8f14e45f-…", "method":"POST",
      "endpoint":"/people/01HZX…/relatives", "payload":{…},
      "client_created_at":"2026-09-01T10:22:31Z" } ] }
```

Server, per operation: look up `(user_id, client_operation_id)` in `sync_operations`.
Found → return the stored response with `"duplicate": true`, apply nothing. Not found →
execute, store the response, return it. Replays are therefore free, and a client that
loses its ack and retries never creates a second grandfather.

```
GET /api/v1/sync/changes?since=2026-08-30T00:00:00Z&scope=branch:01HB…
→ { "people":[…], "relationships":[…], "unions":[…], "deleted":{"people":["01H…"]},
    "server_time":"2026-09-02T09:00:00Z", "has_more":true, "next_cursor":"…" }
```

Delta pull is scoped and cursor-paged; the client never mirrors the whole database.

Conflict rule: **server wins on verified records**, client change becomes a change
request. On unverified records, last-write-wins with the loser preserved as a revision.
The user is told which of their offline edits became proposals.

## 7. Documentation & testing of the API

- OpenAPI 3.1 spec generated from FormRequests + Resources (`scramble` or hand-maintained
  `openapi.yaml`), served at `/docs/api` and exported for the Flutter client.
- Every endpoint has a feature test asserting: happy path, validation failure,
  unauthorised access, **and privacy masking for a viewer outside the scope**.
- The tree endpoint additionally asserts a query-count budget so an N+1 regression fails CI.
