import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/errors/api_exception.dart';
import '../models/tree_graph.dart';
import '../repositories/tree_repository.dart';
import 'app_providers.dart';

final treeRepositoryProvider = Provider<TreeRepository>(
  (ref) => TreeRepository(
    ref.watch(apiClientProvider),
    ref.watch(treeCacheProvider),
  ),
);

/// What the tree screen is currently asking for.
class TreeQuery {
  const TreeQuery({
    required this.focusUlid,
    this.ancestors = 3,
    this.descendants = 2,
  });

  final String focusUlid;
  final int ancestors;
  final int descendants;

  TreeQuery copyWith({String? focusUlid, int? ancestors, int? descendants}) =>
      TreeQuery(
        focusUlid: focusUlid ?? this.focusUlid,
        ancestors: ancestors ?? this.ancestors,
        descendants: descendants ?? this.descendants,
      );

  @override
  bool operator ==(Object other) =>
      other is TreeQuery &&
      other.focusUlid == focusUlid &&
      other.ancestors == ancestors &&
      other.descendants == descendants;

  @override
  int get hashCode => Object.hash(focusUlid, ancestors, descendants);
}

/// The query drives the fetch; changing focus or depth is the only way to move.
///
/// Keeping it in one place means "go to me", tapping a person and expanding a
/// branch are the same operation with different arguments, rather than three
/// code paths that can disagree about what is on screen.
class TreeQueryNotifier extends Notifier<TreeQuery?> {
  @override
  TreeQuery? build() => null;

  void focusOn(String ulid) => state = TreeQuery(focusUlid: ulid);

  /// Re-centres without changing depth, so expanding around a new person does
  /// not silently reset how much they were looking at.
  void recentre(String ulid) =>
      state = (state ?? TreeQuery(focusUlid: ulid)).copyWith(focusUlid: ulid);

  void deepenAncestors() {
    final current = state;
    if (current == null) return;

    state = current.copyWith(ancestors: (current.ancestors + 2).clamp(0, 8));
  }

  void deepenDescendants() {
    final current = state;
    if (current == null) return;

    state = current.copyWith(
      descendants: (current.descendants + 2).clamp(0, 8),
    );
  }

  /// Asks for as many generations as the current zoom can usefully show.
  ///
  /// Pulling back used to make the same few generations smaller, which is the
  /// opposite of what the gesture means: somebody zooming out is asking to see
  /// more of the family, not a smaller picture of the same corner of it.
  ///
  /// Only ever deepens. Zooming back in keeps what has already been fetched,
  /// because throwing away generations somebody just asked for — and refetching
  /// them the moment they pull back again — is worse than holding them.
  void deepenForScale(double scale) {
    final current = state;

    if (current == null) return;

    // Roughly a generation for each halving. At full size three up and two
    // down is a comfortable screenful; at a third of that there is room for
    // eight of each, which is the server's own ceiling.
    final wanted = switch (scale) {
      >= 0.6 => 3,
      >= 0.4 => 5,
      >= 0.25 => 6,
      _ => 8,
    };

    if (wanted <= current.ancestors && wanted <= current.descendants) return;

    state = current.copyWith(
      ancestors: wanted.clamp(current.ancestors, 8),
      descendants: wanted.clamp(current.descendants, 8),
    );
  }
}

final treeQueryProvider = NotifierProvider<TreeQueryNotifier, TreeQuery?>(
  TreeQueryNotifier.new,
);

final treeProvider = FutureProvider<TreeGraph>((ref) async {
  final query = ref.watch(treeQueryProvider);

  if (query == null) return TreeGraph.empty;

  final repository = ref.watch(treeRepositoryProvider);

  try {
    return await repository.tree(
      query.focusUlid,
      ancestors: query.ancestors,
      descendants: query.descendants,
    );
  } on ApiException catch (error) {
    // Only an unreachable server falls back to the device. A 403 or a 404 is
    // the server's answer and must stand — serving a cached copy of a record
    // somebody has since lost access to would be a leak with extra steps.
    if (!error.isOffline) rethrow;

    final cached = await repository.cached(
      query.focusUlid,
      ancestors: query.ancestors,
      descendants: query.descendants,
    );

    if (cached == null) throw const NothingCachedException();

    return cached;
  }
});

/// Whether the app bar, the summary and the bottom bar are out of the way.
///
/// On a phone those three take half the height, and it is the half somebody is
/// trying to read the tree through. They leave when you pull the chart
/// downward — going further down a family — and come back when you pull it
/// back up, which is the gesture every list on the phone already uses.
///
/// Hiding only for the duration of a gesture, as this did first, gives the
/// space back at the moment it stops being useful: you get room while your
/// thumb is moving and lose it the instant you stop to read.
class TreeChromeNotifier extends Notifier<bool> {
  @override
  bool build() => false;

  void hidden(bool value) {
    if (state != value) state = value;
  }
}

final treeChromeHiddenProvider = NotifierProvider<TreeChromeNotifier, bool>(
  TreeChromeNotifier.new,
);
