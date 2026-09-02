import 'person_summary.dart';

/// One union, as the chart needs it: a couple and the children hanging beneath.
class TreeUnion {
  const TreeUnion({
    required this.ulid,
    required this.partnerUlids,
    required this.childUlids,
    this.unionType = 'marriage',
    this.status = 'unknown',
    this.marriageYear,
    this.orderIndex = 1,
  });

  final String ulid;

  /// One or two. A single-parent union is real and common in historical records.
  final List<String> partnerUlids;

  /// Already in birth order — the server sorts them, because birth order is a
  /// recorded fact and not something the client should invent.
  final List<String> childUlids;

  final String unionType;
  final String status;
  final int? marriageYear;
  final int orderIndex;

  bool get isSingleParent => partnerUlids.length < 2;

  factory TreeUnion.fromJson(Map<String, dynamic> json) => TreeUnion(
        ulid: json['ulid'] as String,
        partnerUlids: (json['partners'] as List? ?? const []).map((p) => p.toString()).toList(),
        childUlids: (json['children'] as List? ?? const []).map((c) => c.toString()).toList(),
        unionType: json['union_type'] as String? ?? 'marriage',
        status: json['status'] as String? ?? 'unknown',
        marriageYear: json['marriage_year'] as int?,
        orderIndex: json['order_index'] as int? ?? 1,
      );
}

/// A parent-child edge. `dashed` is the server's decision, not a style choice:
/// adoptive and step relationships are drawn differently because they are
/// different, and the chart should not quietly assert biology.
class TreeEdge {
  const TreeEdge({
    required this.parentUlid,
    required this.childUlid,
    this.kind = 'biological',
    this.dashed = false,
  });

  final String parentUlid;
  final String childUlid;
  final String kind;
  final bool dashed;

  factory TreeEdge.fromJson(Map<String, dynamic> json) => TreeEdge(
        parentUlid: json['parent'] as String,
        childUlid: json['child'] as String,
        kind: json['kind'] as String? ?? 'biological',
        dashed: json['dashed'] as bool? ?? false,
      );
}

/// How much more there is, beyond what was returned.
class Expandable {
  const Expandable({this.children = 0, this.parents = 0});

  final int children;
  final int parents;

  bool get any => children > 0 || parents > 0;
}

/// One traversal of the genealogy graph, as the API returned it.
///
/// A projection, never a stored structure — the same person sits in a different
/// graph depending on who is at the centre.
class TreeGraph {
  const TreeGraph({
    required this.focusUlid,
    required this.people,
    required this.unions,
    required this.edges,
    required this.expandable,
    this.ancestorsDepth = 0,
    this.descendantsDepth = 0,
    this.nodeCount = 0,
    this.truncated = false,
    this.graphVersion = 0,
    this.fromCache = false,
  });

  final String focusUlid;
  final Map<String, PersonSummary> people;
  final List<TreeUnion> unions;
  final List<TreeEdge> edges;

  /// Per person, what was left out — so the UI can offer "+12 more" instead of
  /// letting somebody discover the boundary by tapping into nothing.
  final Map<String, Expandable> expandable;

  final int ancestorsDepth;
  final int descendantsDepth;
  final int nodeCount;
  final bool truncated;
  final int graphVersion;

  /// Rebuilt from the device rather than fetched. The screen says so: a tree
  /// assembled from whatever happens to be cached is necessarily partial, and
  /// presenting a fragment as the whole family is the offline failure that
  /// actually misleads people.
  final bool fromCache;

  PersonSummary? person(String ulid) => people[ulid];

  Expandable expandableFor(String ulid) => expandable[ulid] ?? const Expandable();

  bool get isEmpty => people.isEmpty;

  static const TreeGraph empty = TreeGraph(
    focusUlid: '',
    people: {},
    unions: [],
    edges: [],
    expandable: {},
  );

  factory TreeGraph.fromResponse(Map<String, dynamic> data, Map<String, dynamic> meta) {
    final people = <String, PersonSummary>{};

    for (final raw in (data['people'] as List? ?? const [])) {
      final person = PersonSummary.fromJson((raw as Map).cast<String, dynamic>());
      people[person.ulid] = person;
    }

    final expandable = <String, Expandable>{};

    ((meta['expandable'] as Map?) ?? const {}).forEach((key, value) {
      final counts = (value as Map).cast<String, dynamic>();
      expandable[key.toString()] = Expandable(
        children: counts['children'] as int? ?? 0,
        parents: counts['parents'] as int? ?? 0,
      );
    });

    return TreeGraph(
      focusUlid: data['focus'] as String? ?? '',
      people: people,
      unions: (data['unions'] as List? ?? const [])
          .map((u) => TreeUnion.fromJson((u as Map).cast<String, dynamic>()))
          .toList(growable: false),
      edges: (data['edges'] as List? ?? const [])
          .map((e) => TreeEdge.fromJson((e as Map).cast<String, dynamic>()))
          .toList(growable: false),
      expandable: expandable,
      ancestorsDepth: meta['ancestors_depth'] as int? ?? 0,
      descendantsDepth: meta['descendants_depth'] as int? ?? 0,
      nodeCount: meta['node_count'] as int? ?? people.length,
      truncated: meta['truncated'] as bool? ?? false,
      graphVersion: meta['graph_version'] as int? ?? 0,
    );
  }
}
