import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:go_router/go_router.dart';

import '../../../routing/app_router.dart';
import '../../person/view/person_screen.dart';
import '../../sync/widgets/sync_banner.dart';
import 'package:vector_math/vector_math_64.dart' show Vector3;

import '../../../core/errors/api_exception.dart';
import '../../../models/tree_graph.dart';
import '../../../providers/auth_provider.dart';
import '../../../providers/tree_provider.dart';
import '../layout/tree_layout.dart';
import '../layout/tree_layout_engine.dart';
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
  final _engine = const TreeLayoutEngine();

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
      ..translateByVector3(Vector3(
        viewSize.width / 2 - rect.center.dx * scale,
        viewSize.height / 2 - rect.center.dy * scale,
        0,
      ))
      ..scaleByDouble(scale, scale, scale, 1);
  }

  void _goToMe() {
    final ulid = _myPersonUlid();

    if (ulid == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Your account is not linked to anyone yet. Find yourself in the archive first.'),
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

  /// Opens the full record. Reached by long-press on a card and by tapping
  /// the legend, so it is discoverable without making every tap navigate.
  void _openProfile(String ulid) {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => PersonScreen(ulid: ulid)),
    );
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

    return Scaffold(
      appBar: AppBar(
        title: const Text('Family tree'),
        actions: [
          IconButton(
            tooltip: 'Go to me',
            onPressed: _goToMe,
            icon: const Icon(Icons.my_location),
          ),
        ],
      ),
      body: Column(
        children: [
          SyncBanner(onTap: () => context.push(Routes.pendingChanges)),
          Expanded(child: tree.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _Error(
          message: error is ApiException ? error.message : 'Could not load the tree.',
          onRetry: () => ref.invalidate(treeProvider),
        ),
        data: (graph) {
          if (query == null) return const _NoStartingPoint();
          if (graph.isEmpty) return const _Empty();

          final layout = _engine.layout(graph);

          return LayoutBuilder(
            builder: (context, constraints) {
              // Centre on the focus once per new graph, never on every rebuild:
              // yanking the view back while somebody is panning is maddening.
              if (_centredOn != graph.focusUlid) {
                _centredOn = graph.focusUlid;
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  if (mounted) _centre(layout.focusRect, constraints.biggest);
                });
              }

              return Stack(
                children: [
                  TreeCanvas(
                    graph: graph,
                    layout: layout,
                    controller: _controller,
                    onPersonTap: _onPersonTap,
                    onPersonLongPress: _openProfile,
                    onExpand: _onExpand,
                  ),
                  _Legend(
                    graph: graph,
                    layout: layout,
                    onOpenProfile: _openProfile,
                  ),
                ],
              );
            },
          );
        },
          )),
        ],
      ),
    );
  }
}

/// What is on screen, and what is not.
///
/// The truncation notice matters: a tree that quietly stops is indistinguishable
/// from a family that ends there.
class _Legend extends StatelessWidget {
  const _Legend({
    required this.graph,
    required this.layout,
    required this.onOpenProfile,
  });

  final TreeGraph graph;
  final TreeLayout layout;
  final void Function(String ulid) onOpenProfile;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final focus = graph.person(graph.focusUlid);

    return Positioned(
      left: 12,
      right: 12,
      bottom: 12,
      child: Card(
        child: InkWell(
          onTap: focus == null ? null : () => onOpenProfile(focus.ulid),
          borderRadius: BorderRadius.circular(12),
          child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      focus?.displayName ?? 'Family tree',
                      style: theme.textTheme.titleMedium,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      '${graph.nodeCount} people · '
                      '${graph.ancestorsDepth} up, ${graph.descendantsDepth} down'
                      '${graph.truncated ? ' · showing the nearest' : ''}'
                      // A tree rebuilt from the device is necessarily partial.
                      // Presenting a fragment as the whole family is the
                      // offline failure that actually misleads people.
                      '${graph.fromCache ? ' · saved on this device' : ''}',
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              if (focus?.generationLabel != null)
                Chip(
                  label: Text(focus!.generationLabel!),
                  visualDensity: VisualDensity.compact,
                ),
              if (focus != null)
                Icon(
                  Icons.chevron_right,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
            ],
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
            Icon(Icons.account_tree_outlined, size: 44, color: theme.colorScheme.onSurfaceVariant),
            const SizedBox(height: 16),
            Text('No starting point yet', style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              'Find yourself in the archive, or open somebody from search, and '
              'the tree will start there.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
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
