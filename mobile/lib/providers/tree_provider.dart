import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/tree_graph.dart';
import '../repositories/tree_repository.dart';
import 'app_providers.dart';

final treeRepositoryProvider = Provider<TreeRepository>(
  (ref) => TreeRepository(ref.watch(apiClientProvider)),
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

  TreeQuery copyWith({String? focusUlid, int? ancestors, int? descendants}) => TreeQuery(
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
  void recentre(String ulid) => state = (state ?? TreeQuery(focusUlid: ulid)).copyWith(focusUlid: ulid);

  void deepenAncestors() {
    final current = state;
    if (current == null) return;

    state = current.copyWith(ancestors: (current.ancestors + 2).clamp(0, 8));
  }

  void deepenDescendants() {
    final current = state;
    if (current == null) return;

    state = current.copyWith(descendants: (current.descendants + 2).clamp(0, 8));
  }
}

final treeQueryProvider = NotifierProvider<TreeQueryNotifier, TreeQuery?>(TreeQueryNotifier.new);

final treeProvider = FutureProvider<TreeGraph>((ref) async {
  final query = ref.watch(treeQueryProvider);

  if (query == null) return TreeGraph.empty;

  return ref.watch(treeRepositoryProvider).tree(
        query.focusUlid,
        ancestors: query.ancestors,
        descendants: query.descendants,
      );
});
