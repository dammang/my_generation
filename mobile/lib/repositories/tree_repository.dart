import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
import '../models/tree_graph.dart';

class TreeRepository {
  TreeRepository(this._api);

  final ApiClient _api;

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

    return TreeGraph.fromResponse(envelope.data!, envelope.meta);
  }
}
