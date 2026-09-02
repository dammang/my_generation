import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/core/ulid.dart';
import 'package:my_generation/database/app_database.dart';
import 'package:my_generation/database/tree_cache_dao.dart';
import 'package:my_generation/models/tree_graph.dart';

Map<String, dynamic> _person(String ulid, String name, {bool redacted = false}) => {
      'ulid': ulid,
      'display_name': name,
      'gender': 'male',
      'is_living': false,
      'redacted': redacted,
    };

/// grandfather → father → child, plus an uncle off the grandfather.
TreeGraph _family() => TreeGraph.fromResponse(
      {
        'focus': 'FATHER',
        'people': [
          _person('GRANDFATHER', 'Thawng Dam'),
          _person('FATHER', 'Ngul Muan'),
          _person('CHILD', 'Za Tun'),
          _person('UNCLE', 'Kap Dai'),
        ],
        'edges': [
          {'parent': 'GRANDFATHER', 'child': 'FATHER', 'kind': 'biological'},
          {'parent': 'GRANDFATHER', 'child': 'UNCLE', 'kind': 'biological'},
          {'parent': 'FATHER', 'child': 'CHILD', 'kind': 'biological'},
        ],
        'unions': const [],
      },
      const {'ancestors': 2, 'descendants': 2},
    );

void main() {
  late AppDatabase db;
  late TreeCacheDao cache;

  setUp(() {
    db = AppDatabase(NativeDatabase.memory());
    cache = TreeCacheDao(db);
  });

  tearDown(() => db.close());

  group('the local mirror', () {
    test('rebuilds a tree around a person it has seen', () async {
      await cache.store(_family());

      final graph = await cache.graphAround('FATHER');

      expect(graph, isNotNull);
      expect(graph!.people.keys, containsAll(['GRANDFATHER', 'FATHER', 'CHILD']));
      expect(graph.person('FATHER')!.depth, 0);
      expect(graph.person('GRANDFATHER')!.depth, -1);
      expect(graph.person('CHILD')!.depth, 1);
    });

    test('can centre on somebody the original response was not centred on',
        () async {
      // The device stores a graph, not response snapshots. Caching whole
      // responses would mean only the exact trees somebody had already opened
      // could be reopened offline.
      await cache.store(_family());

      final graph = await cache.graphAround('CHILD', ancestors: 2, descendants: 1);

      expect(graph!.person('CHILD')!.depth, 0);
      expect(graph.person('FATHER')!.depth, -1);
      expect(graph.person('GRANDFATHER')!.depth, -2);
    });

    test('respects the depth asked for', () async {
      await cache.store(_family());

      final graph = await cache.graphAround('CHILD', ancestors: 1, descendants: 0);

      expect(graph!.people.keys, containsAll(['CHILD', 'FATHER']));
      expect(graph.people.containsKey('GRANDFATHER'), isFalse);
    });

    test('says it came from the device', () async {
      await cache.store(_family());

      final graph = await cache.graphAround('FATHER');

      // A tree rebuilt from whatever happens to be cached is necessarily
      // partial, and must not look like a freshly fetched one.
      expect(graph!.fromCache, isTrue);
      expect(graph.truncated, isTrue);
    });

    test('counts the people it actually drew', () async {
      await cache.store(_family());

      final graph = await cache.graphAround('FATHER');

      // The count comes from a response's meta online; offline there is none,
      // and a legend reading "0 people" over a drawn tree is worse than no
      // legend at all.
      expect(graph!.nodeCount, graph.people.length);
      expect(graph.nodeCount, greaterThan(0));
    });

    test('returns nothing for a person it has never seen', () async {
      await cache.store(_family());

      // An empty tree would read as a person with no family rather than as a
      // device with no copy of them.
      expect(await cache.graphAround('STRANGER'), isNull);
    });

    test('keeps a masked person masked', () async {
      await cache.store(TreeGraph.fromResponse(
        {
          'focus': 'FATHER',
          'people': [
            _person('FATHER', 'Ngul Muan'),
            _person('CHILD', 'Someone', redacted: true),
          ],
          'edges': [
            {'parent': 'FATHER', 'child': 'CHILD', 'kind': 'biological'},
          ],
          'unions': const [],
        },
        const {},
      ));

      final graph = await cache.graphAround('FATHER');

      // Going offline must never widen what somebody can read.
      expect(graph!.person('CHILD')!.redacted, isTrue);
    });

    test('drops an edge whose other end was never cached', () async {
      await cache.store(_family());

      final graph = await cache.graphAround('FATHER', ancestors: 0, descendants: 1);

      // An edge to a person outside the walk would draw a line to nowhere.
      for (final edge in graph!.edges) {
        expect(graph.people.containsKey(edge.parentUlid), isTrue);
        expect(graph.people.containsKey(edge.childUlid), isTrue);
      }
    });

    test('signing out leaves nothing behind', () async {
      await cache.store(_family());
      expect(await cache.peopleCount(), 4);

      await db.wipe();

      // The cache holds records the next account may have no right to see.
      expect(await cache.peopleCount(), 0);
    });
  });

  group('immediate family from the device', () {
    _familyFromCacheTests(() => db, () => cache);
  });

  group('client-minted identifiers', () {
    test('are the right shape for the server to accept', () {
      final ulid = Ulid.generate();

      expect(ulid.length, 26);
      expect(RegExp(r'^[0-7][0-9A-HJKMNP-TV-Z]{25}$').hasMatch(ulid), isTrue);
    });

    test('sort by the moment they were made', () {
      final earlier = Ulid.generate(DateTime.utc(2020));
      final later = Ulid.generate(DateTime.utc(2026));

      // Monotonic ids are what keep them indexing well on the server.
      expect(earlier.compareTo(later), lessThan(0));
    });

    test('do not collide', () {
      final ids = {for (var i = 0; i < 500; i++) Ulid.generate()};

      expect(ids.length, 500);
    });
  });
}

/// Immediate family, rebuilt from cached edges.
void _familyFromCacheTests(AppDatabase Function() db, TreeCacheDao Function() cache) {
  test('finds parents, children and siblings from cached edges', () async {
    await cache().store(_family());

    final bundle = await cache().family('FATHER');

    expect(bundle!.parents.single.ulid, 'GRANDFATHER');
    expect(bundle.children.single.ulid, 'CHILD');
    // Derived from shared parents, exactly as the server derives them —
    // storing a derived fact is how the two versions start disagreeing.
    expect(bundle.siblings.single.ulid, 'UNCLE');
    expect(bundle.fromCache, isTrue);
  });

  test('drops union grouping rather than showing it half-populated', () async {
    await cache().store(_family());

    final bundle = await cache().family('FATHER');

    // A union whose children are only partly cached would misrepresent which
    // marriage a child belongs to.
    expect(bundle!.unions, isEmpty);
  });

  test('returns nothing for a person never cached', () async {
    await cache().store(_family());

    expect(await cache().family('STRANGER'), isNull);
  });
}
