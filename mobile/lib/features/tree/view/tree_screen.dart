import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:go_router/go_router.dart';

import '../../../routing/app_router.dart';
import '../../sync/widgets/sync_banner.dart';
import 'package:vector_math/vector_math_64.dart' show Vector3;

import '../../../core/errors/api_exception.dart';
import '../../../models/tree_graph.dart';
import '../../../models/tree_summary.dart';
import '../../../providers/auth_provider.dart';
import '../../../providers/tree_provider.dart';
import '../layout/tree_layout.dart';
import '../layout/tree_layout_engine.dart';
import '../export/tree_exporter.dart';
import '../layout/tree_metrics.dart';
import 'other_family_sheet.dart';
import 'tree_menu_drawer.dart';
import 'tree_canvas.dart';

/// The family tree.
///
/// The chart is the centrepiece of the product, so the controls are the ones
/// somebody actually reaches for: find myself, go up a generation, go down one.
/// Everything else is pinch and pan.
class TreeScreen extends ConsumerStatefulWidget {
  const TreeScreen({super.key, this.initialUlid});

  final String? initialUlid;

  @override
  ConsumerState<TreeScreen> createState() => _TreeScreenState();
}

class _TreeScreenState extends ConsumerState<TreeScreen> {
  final _controller = TransformationController();

  /// Rebuilt per frame from the device's text scale: the card is a fixed box
  /// and the engine has to be told how tall the text inside it will actually
  /// be, or the two disagree and the difference is clipped.
  TreeLayoutEngine _engineFor(BuildContext context) => TreeLayoutEngine(
    metrics: TreeMetrics.forText(
      scaler: MediaQuery.textScalerOf(context),
      // The styles the card itself uses. Anything else here is a guess about
      // the thing being measured.
      name: Theme.of(context).textTheme.labelLarge,
      dates: Theme.of(context).textTheme.labelMedium,
    ),
  );

  String? _centredOn;

  @override
  void initState() {
    super.initState();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final start = widget.initialUlid ?? _myPersonUlid();

      if (start != null) ref.read(treeQueryProvider.notifier).focusOn(start);
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  String? _myPersonUlid() {
    final auth = ref.read(authProvider);

    return auth is AuthSignedIn ? auth.user.personUlid : null;
  }

  /// Puts a rect in the middle of the screen at the current zoom.
  void _centre(Rect rect, Size viewSize) {
    final scale = _controller.value.getMaxScaleOnAxis();

    _controller.value = Matrix4.identity()
      ..translateByVector3(
        Vector3(
          viewSize.width / 2 - rect.center.dx * scale,
          viewSize.height / 2 - rect.center.dy * scale,
          0,
        ),
      )
      ..scaleByDouble(scale, scale, scale, 1);
  }

  void _goToMe() {
    final ulid = _myPersonUlid();

    if (ulid == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Your account is not linked to anyone yet. Find yourself in the archive first.',
          ),
        ),
      );
      return;
    }

    setState(() => _centredOn = null);
    ref.read(treeQueryProvider.notifier).focusOn(ulid);
  }

  void _onPersonTap(String ulid) {
    // Tapping re-centres rather than opening a profile: on a chart, moving is
    // what a tap means, and the profile is one more tap away from there.
    setState(() => _centredOn = null);
    ref.read(treeQueryProvider.notifier).recentre(ulid);
  }

  /// Somebody whose own family is recorded and is not on this chart.
  ///
  /// A wife linked to the family she was born into: her parents and her
  /// brothers and sisters exist, and none of them belong on her husband's
  /// chart. Tapping her shows them, and offers the one thing worth offering —
  /// a way into that family.
  void _openOtherFamily(TreeGraph graph, String ulid) {
    final person = graph.person(ulid);

    if (person == null) return;

    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (_) => OtherFamilySheet(
        person: person,
        onOpenTheirFamily: () => _onPersonTap(ulid),
      ),
    );
  }

  /// Opens the full record. Reached by long-press on a card and by tapping
  /// the legend, so it is discoverable without making every tap navigate.
  ///
  /// Through the router, not a bare MaterialPageRoute: that pushed onto the
  /// branch navigator, so the same destination behaved one way from the tree
  /// and another from search, and the analytics observer saw a route with no
  /// name. It also meant a person opened here could not be linked to.
  void _openProfile(String ulid) {
    context.push(Routes.personPath(ulid));
  }

  bool _exporting = false;

  /// Saves the whole chart as a picture and offers it to whatever the phone
  /// can send a file to.
  ///
  /// The whole chart, not what is on screen: what is on screen is a culled,
  /// panned, zoomed window onto it, and exporting that would export somebody's
  /// scroll position.
  Future<void> _export(TreeGraph graph, TreeLayout layout) async {
    if (_exporting) return;

    setState(() => _exporting = true);

    final messenger = ScaffoldMessenger.of(context);
    final title = graph.person(graph.focusUlid)?.displayName ?? 'Family tree';

    messenger.showSnackBar(
      SnackBar(
        content: Text('Drawing ${graph.people.length} people…'),
        duration: const Duration(seconds: 30),
      ),
    );

    try {
      const exporter = TreeExporter();

      // Counted from the graph, not from what was fetched. A chart drawn three
      // generations deep would otherwise caption itself as a family three
      // generations large — and a caption is the part somebody quotes years
      // later, when nobody remembers how deep the chart was drawn.
      TreeSummary? summary;

      try {
        summary = await ref
            .read(treeRepositoryProvider)
            .summary(graph.focusUlid)
            .timeout(const Duration(seconds: 12));
      } catch (_) {
        // A caption with fewer lines is worth more than a failed export.
        summary = null;
      }

      if (!mounted) return;

      final exported = await exporter.export(
        context: context,
        graph: graph,
        layout: layout,
        title: title,
        summary: summary,
      );

      messenger.hideCurrentSnackBar();
      messenger.showSnackBar(
        SnackBar(content: Text('Saved · ${exported.describe}')),
      );

      await exporter.share(exported, title);
    } catch (error) {
      messenger.hideCurrentSnackBar();
      messenger.showSnackBar(
        SnackBar(content: Text('Could not export the chart. $error')),
      );
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  void _onExpand(String ulid, bool ancestors) {
    setState(() => _centredOn = null);

    final notifier = ref.read(treeQueryProvider.notifier);

    // Expanding means re-centring on that person and asking for more in the
    // direction they tapped — the server decides depth, the client never tries
    // to stitch two responses together.
    notifier.recentre(ulid);

    if (ancestors) {
      notifier.deepenAncestors();
    } else {
      notifier.deepenDescendants();
    }
  }

  @override
  Widget build(BuildContext context) {
    final tree = ref.watch(treeProvider);
    final query = ref.watch(treeQueryProvider);

    final hidden = ref.watch(treeChromeHiddenProvider);

    return Scaffold(
      drawer: TreeMenuDrawer(
        onGoToMe: _goToMe,
        // Null where there is no chart to export: on the empty and error
        // states the entry would do nothing, and an entry that does nothing is
        // read as a broken one.
        onExport: switch (tree.value) {
          final graph? when !graph.isEmpty => () => _export(
            graph,
            _engineFor(context).layout(graph),
          ),
          _ => null,
        },
        exporting: _exporting,
      ),
      // Dragging from the left edge is how somebody pans a chart that runs off
      // the screen, and it would open the menu instead. The button opens it.
      drawerEnableOpenDragGesture: false,
      // No app bar slot: the bar is part of the chart's own stack so it can
      // leave without the chart changing height underneath the finger moving
      // it. A layout that reflows mid-drag makes the tree slide out from
      // under you at the moment you are reading it.
      body: Stack(
        children: [
          Positioned.fill(
            child: tree.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => _Error(
                message: error is ApiException
                    ? error.message
                    : 'Could not load the tree.',
                onRetry: () => ref.invalidate(treeProvider),
              ),
              data: (graph) {
                if (query == null) return const _NoStartingPoint();
                if (graph.isEmpty) return const _Empty();

                final layout = _engineFor(context).layout(graph);

                return LayoutBuilder(
                  builder: (context, constraints) {
                    // Centre on the focus once per new graph, never on every rebuild:
                    // yanking the view back while somebody is panning is maddening.
                    if (_centredOn != graph.focusUlid) {
                      _centredOn = graph.focusUlid;
                      WidgetsBinding.instance.addPostFrameCallback((_) {
                        if (mounted) {
                          _centre(layout.focusRect, constraints.biggest);
                        }
                      });
                    }

                    return Stack(
                      children: [
                        TreeCanvas(
                          graph: graph,
                          layout: layout,
                          controller: _controller,
                          onPersonTap: (ulid) => graph.linkedElsewhere(ulid)
                              ? _openOtherFamily(graph, ulid)
                              : _onPersonTap(ulid),
                          onPersonLongPress: _openProfile,
                          onExpand: _onExpand,
                          onScaleSettled: (scale) => ref
                              .read(treeQueryProvider.notifier)
                              .deepenForScale(scale),
                          onScrolled: (downward) => ref
                              .read(treeChromeHiddenProvider.notifier)
                              .hidden(downward),
                        ),
                        _Legend(
                          graph: graph,
                          layout: layout,
                          onOpenProfile: _openProfile,
                          hidden: hidden,
                          // The chart runs under the bottom bar, so the
                          // summary steps over it rather than hiding behind
                          // it. The bar's own height arrives as padding — and
                          // when the bar has gone there is nothing to step
                          // over, though the summary has gone with it anyway.
                          clearance: MediaQuery.paddingOf(context).bottom,
                        ),
                      ],
                    );
                  },
                );
              },
            ),
          ),
          // The bar and the sync notice leave together: they are one band of
          // screen furniture and hiding half of it would just look broken.
          Positioned(
            left: 0,
            right: 0,
            top: 0,
            child: AnimatedSlide(
              offset: hidden ? const Offset(0, -1) : Offset.zero,
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOut,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  AppBar(
                    centerTitle: true,
                    title: const Text('Family tree'),
                    // Everything but finding somebody lives behind the menu.
                    // Four icons across the top of a chart is four things
                    // competing with the thing they are on top of.
                    leading: Builder(
                      builder: (context) => IconButton(
                        tooltip: 'Menu',
                        icon: const Icon(Icons.menu),
                        onPressed: Scaffold.of(context).openDrawer,
                      ),
                    ),
                    actions: [
                      IconButton(
                        tooltip: 'Find someone',
                        onPressed: () => context.push(Routes.personSearch),
                        icon: const Icon(Icons.search),
                      ),
                    ],
                  ),
                  SyncBanner(onTap: () => context.go(Routes.pendingChanges)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// What is on screen, and what is not.
///
/// The same things the exported picture says, in the space a phone has: where
/// this person stands on both of the clan's scales, and how large the family
/// below them actually is.
///
/// The truncation notice matters: a tree that quietly stops is
/// indistinguishable from a family that ends there.
class _Legend extends ConsumerWidget {
  const _Legend({
    required this.graph,
    required this.layout,
    required this.onOpenProfile,
    this.hidden = false,
    this.clearance = 0,
  });

  final TreeGraph graph;
  final TreeLayout layout;
  final void Function(String ulid) onOpenProfile;

  /// Slid out of the way while the chart is being moved. It describes what is
  /// in the middle of the screen, which is exactly what is changing during a
  /// pan — so it is both useless and in the way at the same moment.
  final bool hidden;

  /// How much of the bottom of the chart something else is sitting on.
  final double clearance;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final focus = graph.person(graph.focusUlid);

    // Counted from the graph, not from what was fetched: a chart drawn three
    // generations deep would otherwise describe a family three generations
    // large. Keyed by the person, so moving away and back does not ask again.
    final summary = ref.watch(treeSummaryProvider(graph.focusUlid)).value;

    final quiet = theme.textTheme.labelMedium?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );

    return Positioned(
      left: 12,
      right: 12,
      bottom: 12 + clearance,
      child: AnimatedSlide(
        offset: hidden ? const Offset(0, 1.4) : Offset.zero,
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOut,
        child: AnimatedOpacity(
          opacity: hidden ? 0 : 1,
          duration: const Duration(milliseconds: 180),
          child: Card(
            child: InkWell(
              onTap: focus == null ? null : () => onOpenProfile(focus.ulid),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 10,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // The generation sits beside the name rather than beneath
                    // it: on a phone that chip took a quarter of the card's
                    // width and left the lines under it wrapping.
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            focus?.displayName ?? 'Family tree',
                            style: theme.textTheme.titleMedium,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (focus?.generationLabel != null) ...[
                          const SizedBox(width: 8),
                          Chip(
                            label: Text(focus!.generationLabel!),
                            visualDensity: VisualDensity.compact,
                            materialTapTargetSize:
                                MaterialTapTargetSize.shrinkWrap,
                            padding: EdgeInsets.zero,
                          ),
                        ],
                        if (focus != null)
                          Icon(
                            Icons.chevron_right,
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                      ],
                    ),

                    // Both of the clan's scales, where it keeps two.
                    if (focus?.generation?.summary case final standing?)
                      Text(standing, style: quiet),

                    // Then the family below them, counted from the graph.
                    if (summary?.shortly case final family?)
                      Text(family, style: quiet),

                    Text(
                      // The family, not the window onto it. A count of what
                      // happened to be fetched reads as a count of how many
                      // relatives somebody has.
                      '${graph.clanPeople} in this clan'
                      '${graph.truncated ? ' · showing the nearest' : ''}'
                      // A tree rebuilt from the device is necessarily partial.
                      // Presenting a fragment as the whole family is the
                      // offline failure that actually misleads people.
                      '${graph.fromCache ? ' · saved on this device' : ''}',
                      style: quiet,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NoStartingPoint extends StatelessWidget {
  const _NoStartingPoint();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.account_tree_outlined,
              size: 44,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 16),
            Text('No starting point yet', style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              'Open somebody and the tree will start there.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 20),
            // This used to be prose telling somebody to use a search that had
            // never been built. An account with no claimed profile arrived
            // here, found nothing to press, and could reach no person — and so
            // could contribute nothing at all, because every screen that
            // writes hangs off a person's page.
            FilledButton.icon(
              onPressed: () => context.push(Routes.personSearch),
              icon: const Icon(Icons.search),
              label: const Text('Find someone'),
            ),
          ],
        ),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty();

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(32),
      child: Text('Nobody to show here yet.', textAlign: TextAlign.center),
    ),
  );
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 40),
          const SizedBox(height: 14),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 20),
          FilledButton(onPressed: onRetry, child: const Text('Try again')),
        ],
      ),
    ),
  );
}
