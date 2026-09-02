import 'package:drift/drift.dart';
import 'package:drift_flutter/drift_flutter.dart';

part 'app_database.g.dart';

/// A local mirror of what the viewer has already been shown.
///
/// Not a copy of the archive. The device holds only records the server has
/// already decided this viewer may see, in the masked form it sent them — so
/// going offline can never widen what somebody can read, and a lost phone
/// cannot leak what the account could not reach.
@DataClassName('CachedPerson')
class CachedPeople extends Table {
  TextColumn get ulid => text()();
  TextColumn get displayName => text()();
  TextColumn get nativeName => text().nullable()();
  TextColumn get gender => text().withDefault(const Constant('unknown'))();
  TextColumn get birthDisplay => text().nullable()();
  IntColumn get birthYear => integer().nullable()();
  TextColumn get deathDisplay => text().nullable()();
  IntColumn get deathYear => integer().nullable()();
  BoolColumn get isLiving => boolean().withDefault(const Constant(true))();

  /// Carried through from the server so the UI can show the same indicator
  /// offline as online. The device never decides this for itself.
  BoolColumn get redacted => boolean().withDefault(const Constant(false))();
  TextColumn get verificationStatus => text().nullable()();
  TextColumn get photoUrl => text().nullable()();
  TextColumn get generationLabel => text().nullable()();
  DateTimeColumn get cachedAt => dateTime()();

  @override
  Set<Column> get primaryKey => {ulid};
}

/// Parent → child, mirrored from the server's derived adjacency.
@DataClassName('CachedEdge')
class CachedEdges extends Table {
  TextColumn get parentUlid => text()();
  TextColumn get childUlid => text()();
  TextColumn get kind => text().withDefault(const Constant('biological'))();

  @override
  Set<Column> get primaryKey => {parentUlid, childUlid, kind};
}

@DataClassName('CachedUnion')
class CachedUnions extends Table {
  TextColumn get ulid => text()();
  TextColumn get partnerUlids => text()();
  TextColumn get childUlids => text()();
  TextColumn get unionType => text().withDefault(const Constant('marriage'))();
  IntColumn get marriageYear => integer().nullable()();
  IntColumn get orderIndex => integer().withDefault(const Constant(1))();

  @override
  Set<Column> get primaryKey => {ulid};
}

/// Writes made while offline, waiting to reach the server.
///
/// Each carries a client-generated operation id. The server keeps an
/// idempotency ledger keyed on it, so a retry after a lost acknowledgement
/// returns the original response instead of creating a second grandfather.
@DataClassName('QueuedOperation')
class SyncQueue extends Table {
  IntColumn get id => integer().autoIncrement()();
  TextColumn get clientOperationId => text()();
  TextColumn get method => text()();
  TextColumn get endpoint => text()();
  TextColumn get payload => text()();
  TextColumn get status => text().withDefault(const Constant('pending'))();
  IntColumn get attempts => integer().withDefault(const Constant(0))();
  TextColumn get lastError => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();
}

@DriftDatabase(tables: [CachedPeople, CachedEdges, CachedUnions, SyncQueue])
class AppDatabase extends _$AppDatabase {
  AppDatabase([QueryExecutor? executor]) : super(executor ?? _open());

  @override
  int get schemaVersion => 1;

  static QueryExecutor _open() => driftDatabase(name: 'my_generation');

  /// Everything cached before a given moment, for eviction.
  Future<int> evictOlderThan(DateTime cutoff) =>
      (delete(cachedPeople)..where((p) => p.cachedAt.isSmallerThanValue(cutoff))).go();

  /// Signing out must leave nothing behind: the cache holds records the next
  /// account may have no right to see.
  Future<void> wipe() async {
    await batch((b) {
      b.deleteAll(cachedPeople);
      b.deleteAll(cachedEdges);
      b.deleteAll(cachedUnions);
      b.deleteAll(syncQueue);
    });
  }
}
