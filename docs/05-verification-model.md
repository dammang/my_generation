# 05 — Verification, Revisions & Collaboration

## 1. The write-path decision

Every write to a genealogical record runs through one gate:

```
                    ┌─ Is the record verification_status = 'verified'? ─┐
                    │                                                   │
                   YES                                                 NO
                    │                                                   │
      ┌─ Does the user hold *.verify in this scope? ─┐        ┌─ Does the user hold *.update? ─┐
      │                                              │        │                                │
     YES                                            NO       YES                              NO
      │                                              │        │                                │
  DIRECT WRITE                              CHANGE REQUEST  DIRECT WRITE              CHANGE REQUEST
  + revision                                (status=pending) + revision                (status=pending)
```

So: **verified genealogy is never silently overwritten.** A contributor correcting a
verified birth year files a proposal; a Family Admin correcting an unverified one just
edits it. Both produce revisions.

New contributors (fewer than N approved contributions, configurable) are routed through
change requests even for unverified records — a light trust ramp that keeps the
verification queue useful rather than overwhelming.

## 2. Change request lifecycle

```
draft → pending ──approve──→ applied  (revisions written, target updated)
           │
           ├──reject────→ rejected      (reason recorded, contributor notified)
           ├──request_info→ needs_info  (contributor can add evidence, returns to pending)
           ├──dispute────→ opens a dispute; CR stays pending until resolved
           └──withdraw───→ withdrawn    (contributor's own action)
```

`ApplyChangeRequest` (one transaction):

1. Re-check the reviewer's permission **at apply time**, not at review time
2. Compare `original_snapshot` against the record's current state. If it changed since
   the proposal, mark `superseded` and surface a three-way diff instead of applying —
   this is how concurrent edits are handled without a lock
3. Apply `payload` to the target (or create it, for `operation = create`)
4. Write one `revisions` row per changed field, carrying `change_request_id` and `source_id`
5. Attach the offered source as a `citation`
6. Set `verification_status = 'verified'`, `verified_by`, `verified_at` if the reviewer
   holds verify permission
7. Bump `graph_version`; queue edge/lineage/match-key rebuilds
8. Increment `contribution_stats` for the requester; notify them
9. Record the review in `change_request_reviews`

Bundled proposals (`parent_change_request_id`) — "add my grandfather **and** link him as
my father's father" — are reviewed and applied as one unit. Partial application would
leave an orphan person.

## 3. Revisions

Written automatically by the `RecordsRevisions` trait on any model declaring
`$revisionable`. One row per field per change. Never updated, never deleted.

```
Person 01HZX… · birth_date
  1921-01-01  →  1923-01-01
  by  @thang   on 2026-04-11
  via change request #4821 (approved by @historian)
  reason  "Church baptism register, Tedim, entry 114"
  source  #331 — Church record, reliability: primary
```

The person's "History" tab is a straight query on
`idx_rev_target (revisionable_type, revisionable_id, created_at)`.

Revisions are also the rollback mechanism: reverting a field writes a *new* revision
restoring the old value. History is append-only in both directions.

## 4. Disputes

A dispute is opened when reviewers disagree, or when a contributor challenges a verified
fact with contrary evidence.

- The record keeps its current accepted value and gains `has_open_dispute = 1`
- Each position becomes a `dispute_claims` row: value, rationale, source
- The API returns `disputed: true` plus the competing claims; the UI shows a badge and
  an "evidence" view comparing sources side by side
- Resolution options: accept one claim, record both (`both_recorded` — the record shows
  "1921 or 1923"), or `insufficient_evidence` (stays disputed, visibly)

Nothing is deleted at any point. A dispute that cannot be settled is a legitimate,
permanent state — that is honest genealogy.

## 5. Verification statuses, precisely

| Status | Meaning |
|---|---|
| `unverified` | Submitted, nobody has reviewed it. The default. Not a criticism. |
| `pending` | A change request touching this record is awaiting review |
| `verified` | A verifier confirmed it, ideally with a citation. Edits now require a change request |
| `disputed` | Competing claims exist. Displayed with the accepted value plus a badge |
| `rejected` | Reviewed and found wrong. Kept for the record; excluded from trees and search |

## 6. Contribution tracking

`contribution_stats` is incremented on write and reconciled nightly against `revisions`.
It powers the "My Contributions" screen:

```
Dam Mang · Contributor · Guite clan
  12 people added        8 relationships        3 stories
  7 sources              41 changes approved    2 pending
```

Attribution is permanent and per-record: `created_by`, `updated_by`, `verified_by` are
shown on every record's Sources/History tab. This is a collaborative archive — knowing
*who* recorded a fact is part of the evidence.

## 7. Notifications (architecture now, delivery in v2)

Laravel notifications with `database` + `fcm` channels. Events already defined in the
MVP domain layer, each with a notification class ready to attach:

| Event | Recipients |
|---|---|
| `ChangeRequestSubmitted` | verifiers in scope |
| `ChangeRequestApproved` / `Rejected` | requester |
| `DisputeOpened` | record contributors + scope verifiers |
| `DuplicateCandidateFound` | scope admins |
| `RelativeAdded` | users whose claimed person is within 2 degrees |
| `ProfileClaimSubmitted` / `Approved` | scope admins / claimant |
| `MembershipApproved` | applicant |

MVP delivers these to the `database` channel (an in-app inbox). Turning on FCM is
config plus `device_tokens`, which already exists — no schema change.
