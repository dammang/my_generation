import '../core/constants/api_paths.dart';
import '../core/errors/api_exception.dart';
import '../core/network/api_client.dart';
import '../database/tree_cache_dao.dart';
import '../models/tree_graph.dart';

class TreeRepository {
  TreeRepository(this._api, this._cache);

  final ApiClient _api;
  final TreeCacheDao _cache;

  /// The subgraph around one person.
  ///
  /// Depth is always bounded — the server refuses anything past its cap rather
  /// than silently clamping — and expansion is asking for a deeper slice
  /// around a new focus, not fetching "the rest".
  Future<TreeGraph> tree(
    String ulid, {
    int ancestors = 3,
    int descendants = 2,
    int? budget,
  }) async {
    final envelope = await _api.get<Map<String, dynamic>>(
      ApiPaths.tree(ulid),
      query: {
        'ancestors': ancestors,
        'descendants': descendants,
        'budget': ?budget,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    final graph = TreeGraph.fromResponse(envelope.data!, envelope.meta);

    // Written on the way past, so the next time the phone has no signal there
    // is something to draw. Only what the server already chose to show this
    // viewer is stored, in the masked form it sent.
    await _cache.store(graph);

    return graph;
  }

  /// The tree as the device holds it.
  ///
  /// Used when the server cannot be reached. Returns null when this person was
  /// never cached — an empty tree would read as a person with no family rather
  /// than as a device with no copy of them.
  Future<TreeGraph?> cached(
    String ulid, {
    int ancestors = 3,
    int descendants = 2,
  }) =>
      _cache.graphAround(ulid, ancestors: ancestors, descendants: descendants);
}

/// Thrown when there is neither a connection nor a cached copy.
class NothingCachedException extends ApiException {
  const NothingCachedException()
      : super(
          message: 'You are offline and this part of the family is not saved '
              'on this device yet.',
        );
}
