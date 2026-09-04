import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/change_request.dart';
import '../../../models/person_detail.dart';
import '../../../models/person_summary.dart';
import '../../../models/story.dart';
import '../../../providers/person_provider.dart';
import '../../../providers/review_provider.dart';
import '../../../providers/story_provider.dart';
import '../../../providers/tree_provider.dart';
import '../../../repositories/person_repository.dart';
import '../../../routing/app_router.dart';
import '../widgets/family_tab.dart';
import '../widgets/history_tab.dart';
import '../widgets/person_header.dart';
import '../widgets/stories_tab.dart';
import '../widgets/timeline_tab.dart';
import 'add_event_screen.dart';
import 'add_relative_screen.dart';
import 'edit_person_screen.dart';
import 'raise_dispute_screen.dart';
import 'story_screen.dart';
import 'write_story_screen.dart';

/// One person, in full.
///
/// Three tabs because a profile answers three different questions — who they
/// were, who they belonged to, and what happened to them — and each is fetched
/// separately so the header appears without waiting on the rest.
class PersonScreen extends ConsumerWidget {
  const PersonScreen({super.key, required this.ulid, this.initialTab = 0});

  final String ulid;

  /// Which tab to open on. A link can point at somebody's family or their
  /// story rather than always landing on the same summary.
  final int initialTab;

  /// Maps the `tab` query parameter of a deep link onto a tab index.
  static int tabIndexFor(String? name) => switch (name) {
        'family' => 1,
        'timeline' => 2,
        'history' => 3,
        _ => 0,
      };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final person = ref.watch(personProvider(ulid));

    return Scaffold(
      body: person.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _Failure(
          error: error,
          onRetry: () => ref.invalidate(personProvider(ulid)),
        ),
        data: (detail) => _Loaded(detail: detail, initialTab: initialTab),
      ),
    );
  }
}

class _Loaded extends ConsumerStatefulWidget {
  const _Loaded({required this.detail, required this.initialTab});

  final PersonDetail detail;
  final int initialTab;

  @override
  ConsumerState<_Loaded> createState() => _LoadedState();
}

class _LoadedState extends ConsumerState<_Loaded>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(
    length: 5,
    initialIndex: widget.initialTab,
    vsync: this,
  );

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  PersonDetail get detail => widget.detail;

  void _openPerson(PersonSummary person) {
    // Pushed rather than replaced: walking up a family and back down again is
    // how people actually read a tree, and the back stack is that walk.
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => PersonScreen(ulid: person.ulid)));
  }

  Future<void> _addRelative(String relation) async {
    final result = await Navigator.of(context).push<AddRelativeResult>(
      MaterialPageRoute(
        builder: (_) => AddRelativeScreen(
          anchorUlid: detail.ulid,
          anchorName: detail.displayName,
          initialRelation: relation,
        ),
      ),
    );

    if (result == null || !mounted) return;

    showAddRelativeOutcome(context, result: result);
  }

  Future<void> _edit() async {
    final outcome = await Navigator.of(context).push<EditOutcome>(
      MaterialPageRoute(builder: (_) => EditPersonScreen(detail: detail)),
    );

    if (outcome == null || !mounted) return;

    showEditOutcome(context, outcome);
  }

  Future<void> _raiseDispute() async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => RaiseDisputeScreen(
          personUlid: detail.ulid,
          personName: detail.displayName,
        ),
      ),
    );
  }

  Future<void> _writeStory() async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => WriteStoryScreen(
          personUlid: detail.ulid,
          personName: detail.displayName,
        ),
      ),
    );
  }

  void _openStory(Story story) {
    Navigator.of(context).push<void>(
      MaterialPageRoute(builder: (_) => StoryScreen(ulid: story.ulid)),
    );
  }

  Future<void> _addEvent() async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => AddEventScreen(
          personUlid: detail.ulid,
          personName: detail.displayName,
        ),
      ),
    );
  }

  /// Hands the tree a new focus and goes there.
  ///
  /// The tree keeps its own depth settings, so this re-centres rather than
  /// resetting how much of the family somebody had opened up.
  void _showInTree() {
    ref.read(treeQueryProvider.notifier).focusOn(detail.ulid);
    context.go('${Routes.tree}?person=${detail.ulid}');
  }

  @override
  Widget build(BuildContext context) {
    final family = ref.watch(familyProvider(detail.ulid));
    final timeline = ref.watch(timelineProvider(detail.ulid));
    final stories = ref.watch(personStoriesProvider(detail.ulid));

    return Scaffold(
      body: NestedScrollView(
        headerSliverBuilder: (context, _) => [
          SliverAppBar(
            pinned: true,
            expandedHeight: 0,
            title: Text(detail.displayName),
            actions: [
              IconButton(
                tooltip: 'Correct this record',
                icon: const Icon(Icons.edit_outlined),
                onPressed: _edit,
              ),
              IconButton(
                tooltip: 'Show in the tree',
                icon: const Icon(Icons.account_tree_outlined),
                onPressed: _showInTree,
              ),
            ],
          ),
          SliverToBoxAdapter(child: PersonHeader(detail: detail)),
          SliverPersistentHeader(
            pinned: true,
            delegate: _TabBarHeader(
              TabBar(
                controller: _tabs,
                isScrollable: true,
                tabAlignment: TabAlignment.start,
                tabs: const [
                  Tab(text: 'Overview'),
                  Tab(text: 'Family'),
                  Tab(text: 'Timeline'),
                  Tab(text: 'Stories'),
                  Tab(text: 'History'),
                ],
              ),
              Theme.of(context).colorScheme.surface,
            ),
          ),
        ],
        body: TabBarView(
          controller: _tabs,
          children: [
            _OverviewTab(detail: detail),
            family.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => _Failure(
                error: error,
                onRetry: () => ref.invalidate(familyProvider(detail.ulid)),
              ),
              data: (bundle) => FamilyTab(
                bundle: bundle,
                onOpenPerson: _openPerson,
                onAddRelative: _addRelative,
              ),
            ),
            timeline.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => _Failure(
                error: error,
                onRetry: () => ref.invalidate(timelineProvider(detail.ulid)),
              ),
              data: (data) =>
                  TimelineTab(timeline: data, onAddEvent: _addEvent),
            ),
            stories.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => _Failure(
                error: error,
                onRetry: () => ref.invalidate(personStoriesProvider(detail.ulid)),
              ),
              data: (data) => StoriesTab(
                stories: data,
                onOpen: _openStory,
                onWrite: _writeStory,
              ),
            ),
            _HistoryPane(ulid: detail.ulid, onRaiseDispute: _raiseDispute),
          ],
        ),
      ),
      floatingActionButton: AnimatedBuilder(
        animation: _tabs,
        builder: (context, _) => switch (_tabs.index) {
          1 => FloatingActionButton.extended(
            onPressed: () => _addRelative('child'),
            icon: const Icon(Icons.person_add_alt),
            label: const Text('Add relative'),
          ),
          2 => FloatingActionButton.extended(
            onPressed: _addEvent,
            icon: const Icon(Icons.add),
            label: const Text('Add event'),
          ),
          3 => FloatingActionButton.extended(
            onPressed: _writeStory,
            icon: const Icon(Icons.edit_outlined),
            label: const Text('Write a story'),
          ),
          _ => const SizedBox.shrink(),
        },
      ),
    );
  }
}

/// History and disputes arrive separately; the tab needs both before it can
/// tell "no changes" apart from "changes you may not see".
class _HistoryPane extends ConsumerWidget {
  const _HistoryPane({required this.ulid, required this.onRaiseDispute});

  final String ulid;
  final VoidCallback onRaiseDispute;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final history = ref.watch(historyProvider(ulid));
    final disputes = ref.watch(disputesProvider(ulid));

    return history.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => _Failure(
        error: error,
        onRetry: () => ref.invalidate(historyProvider(ulid)),
      ),
      data: (data) => HistoryTab(
        history: data,
        disputes: disputes.value ?? const [],
        onRaiseDispute: onRaiseDispute,
      ),
    );
  }
}

class _OverviewTab extends StatelessWidget {
  const _OverviewTab({required this.detail});

  final PersonDetail detail;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final person = detail.summary;

    final facts = <(String, String)>[
      if (person.birthDisplay != null) ('Born', person.birthDisplay!),
      if (detail.birthPlace != null) ('Birthplace', detail.birthPlace!),
      if (person.deathDisplay != null) ('Died', person.deathDisplay!),
      if (detail.deathPlace != null) ('Died at', detail.deathPlace!),
      if (detail.tribeName != null) ('Tribe', detail.tribeName!),
      if (detail.clanName != null) ('Clan', detail.clanName!),
      if (detail.branchName != null) ('Family branch', detail.branchName!),
      if (person.generationLabel != null)
        ('Generation', person.generationLabel!),
    ];

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 96),
      children: [
        if (facts.isEmpty && detail.biography == null)
          Text(
            detail.fromCache
                // Absent, not empty: only what a tree response carried is
                // stored, so "nothing recorded" would be a lie about the
                // record rather than a statement about the device.
                ? 'Only the basics are saved on this device. Connect to see '
                    'the full record.'
                : person.redacted
                    ? 'The details of this record are not shown to you.'
                    : 'Nothing has been recorded about this person yet.',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        for (final (label, value) in facts)
          Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 118,
                  child: Text(
                    label,
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
                Expanded(child: Text(value, style: theme.textTheme.bodyLarge)),
              ],
            ),
          ),
        if (detail.biography != null) ...[
          const SizedBox(height: 10),
          Text('Biography', style: theme.textTheme.labelLarge),
          const SizedBox(height: 8),
          Text(detail.biography!, style: theme.textTheme.bodyMedium),
        ],
      ],
    );
  }
}

/// Keeps the tab bar pinned with an opaque background as content scrolls under.
class _TabBarHeader extends SliverPersistentHeaderDelegate {
  const _TabBarHeader(this.tabBar, this.background);

  final TabBar tabBar;
  final Color background;

  @override
  double get minExtent => tabBar.preferredSize.height;

  @override
  double get maxExtent => tabBar.preferredSize.height;

  @override
  Widget build(BuildContext context, double offset, bool overlaps) =>
      Material(color: background, child: tabBar);

  @override
  bool shouldRebuild(_TabBarHeader old) =>
      old.tabBar != tabBar || old.background != background;
}

class _Failure extends StatelessWidget {
  const _Failure({required this.error, required this.onRetry});

  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final api = error is ApiException ? error as ApiException : null;

    // A record this viewer may not see is indistinguishable from one that does
    // not exist — by design, on the server. The wording here has to hold that
    // line too, rather than hinting that something is being hidden.
    final message = api?.isNotFound ?? false
        ? 'This person is not available to you.'
        : api?.message ?? 'Something went wrong.';

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.person_off_outlined,
              size: 44,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 14),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 18),
            OutlinedButton(onPressed: onRetry, child: const Text('Try again')),
          ],
        ),
      ),
    );
  }
}
