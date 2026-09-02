# 07 — Project Structure

## 1. Laravel

Standard Laravel 12 skeleton with a thin domain layer. No DDD ceremony, no repository
wrapper over Eloquent, no interface for every class. Classes exist where they remove
duplication or hold a transaction — nowhere else.

```
app/
  Actions/                      one public method, one transaction, no HTTP knowledge
    Genealogy/
      AddRelative.php                 relation label → rows (§03 §6)
      CreatePerson.php
      UpdatePerson.php
      CreateUnion.php
      AddChildToUnion.php
      RemoveChildFromUnion.php
      CreateRelationship.php
      MergePeople.php
      RevertPersonMerge.php
      AssertNoCycle.php
    Verification/
      SubmitChangeRequest.php
      ApplyChangeRequest.php
      RejectChangeRequest.php
      OpenDispute.php
      ResolveDispute.php
    Claims/
      SubmitProfileClaim.php
      ApproveProfileClaim.php
    Media/
      StoreMedia.php
      GenerateConversions.php

  Services/                     stateful capabilities, queried many times
    Tree/
      TreeTraversalService.php        the CTEs
      TreeCache.php                   key building, tagging, graph_version
      LineageDepthService.php
      RelationshipPathFinder.php      "how am I related to X"
    Privacy/
      ViewerScopeResolver.php
      PersonVisibilityResolver.php
      FieldMask.php
    Permissions/
      PermissionResolver.php          scoped roles + path prefix matching
    Matching/
      MatchKeyGenerator.php           transliteration + phonetics
      DuplicateScorer.php
    Search/
      PersonSearchService.php
    Statistics/
      FamilyStatisticsService.php
    Sync/
      SyncBatchProcessor.php          idempotency ledger
      DeltaChangeService.php

  Models/
    User, Person, PersonName, Relationship, Union, UnionChild, FamilyEdge,
    LineageDepth, Tribe, Clan, FamilyBranch, Generation, Scope, Membership,
    Place, EventType, PersonEvent, Story, StoryPerson, OralHistory,
    Media, Source, Citation, ChangeRequest, ChangeRequestReview, Revision,
    Dispute, DisputeClaim, DuplicateCandidate, PersonMerge, PersonMatchKey,
    ProfileClaim, ShareLink, SyncOperation, ContributionStat, AuditLog, DeviceToken
    Concerns/
      HasUlid.php
      Contributable.php               created_by/updated_by/verified_by
      RecordsRevisions.php            $revisionable field list
      SoftDeletesWithUniqueness.php   deleted_token handling
      HasVerificationStatus.php
      HasPrivacyLevel.php
      HasUncertainDates.php           the 4-column date pattern + casts

  Http/
    Controllers/Api/V1/             AuthController, PersonController, TreeController,
                                    RelationshipController, UnionController, TribeController,
                                    ClanController, FamilyBranchController, PersonEventController,
                                    StoryController, SourceController, MediaController,
                                    SearchController, PlaceController, ChangeRequestController,
                                    DisputeController, DuplicateController, ProfileClaimController,
                                    SyncController, ShareLinkController, StatisticsController
    Requests/V1/                    one FormRequest per write endpoint; authorize() → Policy
    Resources/V1/                   PersonResource, PersonCardResource, TreeResource,
                                    UnionResource, RelationshipResource, …
                                    (every person serialisation goes through FieldMask)
    Middleware/
      ResolveViewerScope.php
      EnsureMembership.php

  Policies/                       PersonPolicy, RelationshipPolicy, UnionPolicy, TribePolicy,
                                  ClanPolicy, FamilyBranchPolicy, StoryPolicy, SourcePolicy,
                                  MediaPolicy, ChangeRequestPolicy, ProfileClaimPolicy

  Filament/                       Resources/, Widgets/, Pages/  (§ Phase 8)

  Jobs/                           RebuildFamilyEdges, RecomputeLineageDepth, BumpGraphVersion,
                                  GeneratePersonMatchKeys, ScanForDuplicates, GenerateConversions,
                                  RollupStatistics, RecheckLivingStatus, WarmTreeCache

  Events/ Listeners/ Observers/   PersonObserver, RelationshipObserver, UnionObserver
                                  (edge projection + revisions + cache busting)

  Enums/                          Gender, DatePrecision, PrivacyLevel, VerificationStatus,
                                  RelationshipType, RelationshipSubtype, UnionType, UnionStatus,
                                  ChangeRequestStatus, SourceType, EdgeKind
                                  (PHP 8.1 backed enums, cast on the models)

  Exceptions/                     GenealogyRuleException, CycleDetectedException,
                                  AmbiguousUnionException, ChangeRequestSupersededException

  Support/
    ApiResponse.php               the single envelope builder
    GenealogyWarnings.php         warning codes + messages (translatable)

config/genealogy.php              depth caps, node budget, living max age, matching
                                  thresholds, transliteration rules, trust ramp
database/
  migrations/                     ordered per §02 §9
  factories/                      PersonFactory with realistic uncertain dates
  seeders/                        RolePermissionSeeder, EventTypeSeeder, PlaceSeeder(ISO countries),
                                  DemoTribeSeeder (dev only — never in production)
routes/api.php  routes/web.php  routes/console.php
tests/
  Feature/Api/                    per endpoint, incl. a privacy test each
  Feature/Genealogy/              traversal, generations, add-relative, merge, cycles
  Unit/                           visibility masks, matching, date parsing, permissions
```

**Seeders and demo data.** `DemoTribeSeeder` exists so the tree UI can be developed
against realistic shapes, and is gated behind `app()->environment('local')`. It is
**not** the product's data model — every screen reads the real API against the real
schema. No hardcoded family tree ever ships.

## 2. Flutter

```
lib/
  main.dart
  app.dart                        MaterialApp.router, theme, locale
  core/
    constants/                    api paths, sizes, durations
    errors/                       Failure types, exception → Failure mapping
    network/                      DioClient, AuthInterceptor, RetryInterceptor,
                                  ConnectivityService, ApiException
    theme/                        colors, typography (large-type friendly), spacing
    utils/                        date formatting for uncertain dates, name formatting
    extensions/
  config/                         Env (compile-time --dart-define), feature flags
  models/                         freezed + json_serializable
    person.dart  relationship.dart  union_model.dart  tribe.dart  clan.dart
    family_branch.dart  place.dart  person_event.dart  story.dart  source.dart
    tree_graph.dart                the parsed tree payload
    api_response.dart              envelope + warnings
  database/                       Drift
    app_database.dart
    tables/                       people, relationships, unions, union_children,
                                  places, generations, stories, sync_queue, meta
    daos/                         PersonDao, TreeDao, SyncDao
  services/                       AuthService, PersonService, TreeService, SearchService,
                                  MediaService, SyncService, SecureStorageService,
                                  NotificationService
  repositories/                   the one place API and SQLite are reconciled
    person_repository.dart        remote-first, cache-fallback, offline-write-queue
    tree_repository.dart
    tribe_repository.dart
    story_repository.dart
  providers/                      Riverpod providers + notifiers
    auth_provider.dart  tree_provider.dart  person_provider.dart
    connectivity_provider.dart  sync_provider.dart
  features/
    auth/                         login, register, forgot password
    home/                         greeting, my lineage strip, stats, recent activity
    tree/
      view/tree_screen.dart
      widgets/person_card.dart, union_connector.dart, tree_canvas.dart,
              expand_button.dart, tree_controls.dart
      layout/tree_layout_engine.dart     graph → positioned nodes (layered layout)
      layout/tree_painter.dart           CustomPainter for connectors
    person/                       profile, tabs (overview/family/timeline/stories/photos/sources)
    add_relative/                 the guided relation flow
    search/
    community/                    tribe, clan, family branch pages
    profile/                      me, my contributions, settings
    contributions/                change requests, my submissions
  routing/                        app_router.dart (GoRouter), routes.dart, guards.dart
  sync/                           sync_queue.dart, sync_engine.dart, conflict_resolver.dart
  widgets/                        shared: AppButton, PersonAvatar, VerificationBadge,
                                  UncertainDateText, PrivacyLockedCard, EmptyState,
                                  OfflineBanner, LoadingShimmer
  l10n/                           app_en.arb, app_my.arb, app_tdd.arb (no hardcoded strings)
test/
  models/  repositories/  tree_layout/  sync/  widget/
```

**Tree rendering approach.** `InteractiveViewer` for pan/zoom over a `CustomPaint`
canvas. The layout engine converts the API graph into positioned nodes once per fetch
(pure function, unit-testable without a widget); the painter draws connectors; person
cards are real widgets positioned by `Stack` so they stay tappable and accessible.
Only nodes within the visible viewport plus a margin are built — a 400-node tree must
not build 400 widgets at once.

**Offline.** Every fetched person/relationship/union is written to Drift. Writes go to
`sync_queue` with a client ULID and `client_operation_id`, and are flushed by
`SyncEngine` when connectivity returns. The `OfflineBanner` shows Online / Offline /
Syncing (n) at all times so the user is never guessing.

**Secure storage.** Sanctum token in `flutter_secure_storage` (Keychain / EncryptedSharedPreferences),
never in SharedPreferences, never in Drift.
