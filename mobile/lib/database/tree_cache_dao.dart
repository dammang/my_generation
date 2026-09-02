import 'dart:convert';

import 'package:drift/drift.dart';

import '../models/family_bundle.dart';
import '../models/person_summary.dart';
import '../models/tree_graph.dart';
import 'app_database.dart';

/// The local mirror, written from what the server has already shown this
/// viewer and read back when it cannot be reached.
///
/// The device stores a **graph**, not a set of response snapshots. Caching whole
/// responses would mean only the exact trees somebody had already opened could
/// be reopened offline; storing people and edges lets any cached person become
/// a focus, which is what somebody on a plane actually wants.
class TreeCacheDao {
  TreeCacheDao(this._db);

  final AppDatabase _db;

  /// Records everything a tree response contained.
  ///
  /// Masked people are stored exactly as the server sent them — going offline
  /// must never widen what somebody can read.
  Future<void> store(TreeGraph graph) async {
    if (graph.people.isEmpty) return;

    final now = DateTime.now();

    await _db.batch((b) {
      b.insertAllOnConflictUpdate(
        _db.cachedPeople,
        [
          for (final person in graph.people.values)
            CachedPeopleCompanion.insert(
              ulid: person.ulid,
              displayName: person.displayName,
              nativeName: Value(person.nativeName),
              gender: Value(person.gender),
              birthDisplay: Value(person.birthDisplay),
              birthYear: Value(person.birthYear),
              deathDisplay: Value(person.deathDisplay),
              deathYear: Value(person.deathYear),
              isLiving: Value(person.isLiving),
              redacted: Value(person.redacted),
              verificationStatus: Value(person.verificationStatus),
              photoUrl: Value(person.photoUrl),
              generationLabel: Value(person.generationLabel),
              cachedAt: now,
            ),
        ],
      );

      b.insertAllOnConflictUpdate(
        _db.cachedEdges,
        [
          for (final edge in graph.edges)
            CachedEdgesCompanion.insert(
              parentUlid: edge.parentUlid,
              childUlid: edge.childUlid,
              kind: Value(edge.kind),
            ),
        ],
      );

      b.insertAllOnConflictUpdate(
        _db.cachedUnions,
        [
          for (final union in graph.unions)
            CachedUnionsCompanion.insert(
              ulid: union.ulid,
              partnerUlids: jsonEncode(union.partnerUlids),
              childUlids: jsonEncode(union.childUlids),
              unionType: Value(union.unionType),
              marriageYear: Value(union.marriageYear),
            ),
        ],
      );
    });
  }

  Future<bool> has(String ulid) async {
    final row = await (_db.select(_db.cachedPeople)..where((p) => p.ulid.equals(ulid)))
        .getSingleOrNull();

    return row != null;
  }

  /// Rebuilds a subgraph around [focusUlid] from what is stored.
  ///
  /// The same depth-limited walk the server does, over the edges the device
  /// happens to hold. Returns null when the focus is not cached at all —
  /// showing an empty tree would look like a person with no family rather than
  /// a device with no copy.
  Future<TreeGraph?> graphAround(
    String focusUlid, {
    int ancestors = 3,
    int descendants = 2,
  }) async {
    if (!await has(focusUlid)) return null;

    final edges = await _db.select(_db.cachedEdges).get();

    final parentsOf = <String, List<CachedEdge>>{};
    final childrenOf = <String, List<CachedEdge>>{};

    for (final edge in edges) {
      (parentsOf[edge.childUlid] ??= []).add(edge);
      (childrenOf[edge.parentUlid] ??= []).add(edge);
    }

    final depths = <String, int>{focusUlid: 0};
    final kept = <TreeEdge>{};

    // Breadth-first in each direction, exactly like the server's walker: a
    // recursive descent would enumerate paths, which is exponential in a graph
    // where cousins have married.
    void walk(Map<String, List<CachedEdge>> adjacency, int limit, int sign) {
      var frontier = <String>{focusUlid};

      for (var depth = 1; depth <= limit; depth++) {
        final next = <String>{};

        for (final ulid in frontier) {
          for (final edge in adjacency[ulid] ?? const <CachedEdge>[]) {
            final other = sign < 0 ? edge.parentUlid : edge.childUlid;

            kept.add(TreeEdge(
              parentUlid: edge.parentUlid,
              childUlid: edge.childUlid,
              kind: edge.kind,
            ));

            if (!depths.containsKey(other)) {
              depths[other] = sign * depth;
              next.add(other);
            }
          }
        }

        if (next.isEmpty) break;
        frontier = next;
      }
    }

    walk(parentsOf, ancestors, -1);
    walk(childrenOf, descendants, 1);

    final rows = await (_db.select(_db.cachedPeople)
          ..where((p) => p.ulid.isIn(depths.keys.toList())))
        .get();

    final people = <String, PersonSummary>{
      for (final row in rows) row.ulid: _toSummary(row, depths[row.ulid]),
    };

    // An edge whose other end was never cached would draw a line to nowhere.
    final drawable = kept
        .where((e) => people.containsKey(e.parentUlid) && people.containsKey(e.childUlid))
        .toList(growable: false);

    final unionRows = await _db.select(_db.cachedUnions).get();

    final unions = [
      for (final row in unionRows)
        TreeUnion(
          ulid: row.ulid,
          partnerUlids: (jsonDecode(row.partnerUlids) as List).cast<String>(),
          childUlids: (jsonDecode(row.childUlids) as List).cast<String>(),
          unionType: row.unionType,
          marriageYear: row.marriageYear,
        ),
    ].where((u) => u.partnerUlids.any(people.containsKey)).toList(growable: false);

    return TreeGraph(
      focusUlid: focusUlid,
      people: people,
      edges: drawable,
      unions: unions,
      ancestorsDepth: ancestors,
      descendantsDepth: descendants,
      // Counted here rather than carried from a response: offline there is no
      // meta to read it from, and a legend reading "0 people" over a drawn
      // tree is worse than no legend at all.
      nodeCount: people.length,
      // True whether the walk hit its limit or simply ran out of cached graph.
      // Offline the tree is necessarily partial, and the screen must say so
      // rather than presenting a fragment as the whole family.
      truncated: true,
      expandable: const {},
      fromCache: true,
    );
  }

  PersonSummary _toSummary(CachedPerson row, int? depth) => PersonSummary(
        ulid: row.ulid,
        displayName: row.displayName,
        gender: row.gender,
        isLiving: row.isLiving,
        redacted: row.redacted,
        nativeName: row.nativeName,
        birthDisplay: row.birthDisplay,
        birthYear: row.birthYear,
        deathDisplay: row.deathDisplay,
        deathYear: row.deathYear,
        photoUrl: row.photoUrl,
        verificationStatus: row.verificationStatus,
        generationLabel: row.generationLabel,
        depth: depth,
      );

  Future<PersonSummary?> person(String ulid) async {
    final row = await (_db.select(_db.cachedPeople)..where((p) => p.ulid.equals(ulid)))
        .getSingleOrNull();

    return row == null ? null : _toSummary(row, null);
  }

  /// The immediate family, from the edges the device holds.
  ///
  /// Parents and children come from cached edges; siblings are derived from
  /// shared parents exactly as the server derives them, rather than stored —
  /// storing a derived fact is how the two versions start disagreeing.
  Future<FamilyBundle?> family(String ulid) async {
    final self = await person(ulid);

    if (self == null) return null;

    final edges = await _db.select(_db.cachedEdges).get();

    final parentUlids = edges.where((e) => e.childUlid == ulid).map((e) => e.parentUlid).toSet();
    final childUlids = edges.where((e) => e.parentUlid == ulid).map((e) => e.childUlid).toSet();

    final siblingUlids = edges
        .where((e) => parentUlids.contains(e.parentUlid) && e.childUlid != ulid)
        .map((e) => e.childUlid)
        .toSet();

    final unionRows = await _db.select(_db.cachedUnions).get();

    final spouseUlids = <String>{};

    for (final row in unionRows) {
      final partners = (jsonDecode(row.partnerUlids) as List).cast<String>();

      if (partners.contains(ulid)) {
        spouseUlids.addAll(partners.where((p) => p != ulid));
      }
    }

    Future<List<PersonSummary>> load(Set<String> ulids) async {
      if (ulids.isEmpty) return const [];

      final rows = await (_db.select(_db.cachedPeople)
            ..where((p) => p.ulid.isIn(ulids.toList())))
          .get();

      return rows.map((row) => _toSummary(row, null)).toList(growable: false);
    }

    return FamilyBundle(
      person: self,
      parents: await load(parentUlids),
      children: await load(childUlids),
      siblings: await load(siblingUlids),
      spouses: await load(spouseUlids),
      // Unions are cached, but their children lists reference people who may
      // not be. Grouping is dropped offline rather than shown half-populated,
      // which would misrepresent which marriage a child belongs to.
      unions: const [],
      fromCache: true,
    );
  }

  Future<int> peopleCount() async {
    final count = _db.cachedPeople.ulid.count();

    return await (_db.selectOnly(_db.cachedPeople)..addColumns([count]))
        .map((row) => row.read(count) ?? 0)
        .getSingle();
  }
}
