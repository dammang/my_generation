import 'dart:convert';
import 'dart:math';

import 'package:drift/drift.dart';

import '../core/constants/api_paths.dart';
import '../core/errors/api_exception.dart';
import '../core/network/api_client.dart';
import '../database/app_database.dart';

/// One write still waiting to reach the server.
class PendingWrite {
  const PendingWrite({
    required this.id,
    required this.clientOperationId,
    required this.kind,
    required this.payload,
    required this.status,
    required this.attempts,
    this.subjectLabel,
    this.lastError,
  });

  final int id;
  final String clientOperationId;
  final String kind;
  final Map<String, dynamic> payload;
  final String status;
  final int attempts;
  final String? subjectLabel;
  final String? lastError;

  bool get isPending => status == 'pending';
  bool get isRejected => status == 'rejected';

  /// What the person actually did, in their words.
  String describe() {
    final who = subjectLabel ?? 'someone';

    return switch (kind) {
      'add_relative' => 'A ${payload['relation'] ?? 'relative'} for $who',
      'add_event' => '${payload['title'] ?? 'An event'} for $who',
      'edit_person' => 'A correction to $who',
      _ => 'A change to $who',
    };
  }

  factory PendingWrite.fromRow(QueuedOperation row) => PendingWrite(
        id: row.id,
        clientOperationId: row.clientOperationId,
        kind: row.kind,
        payload: (jsonDecode(row.payload) as Map).cast<String, dynamic>(),
        status: row.status,
        attempts: row.attempts,
        subjectLabel: row.subjectLabel,
        lastError: row.lastError,
      );
}

/// The result of trying to drain the queue.
class SyncOutcome {
  const SyncOutcome({
    required this.applied,
    required this.failed,
    required this.stillWaiting,
    this.unreachable = false,
  });

  const SyncOutcome.offline()
      : applied = 0,
        failed = 0,
        stillWaiting = 0,
        unreachable = true;

  final int applied;
  final int failed;
  final int stillWaiting;

  /// The server could not be reached at all. Not a failure of the writes —
  /// they stay queued, untouched, and nothing about them should be reported to
  /// the person as having gone wrong.
  final bool unreachable;

  bool get didSomething => applied > 0 || failed > 0;
}

/// Writes made offline, and getting them to the server exactly once.
class SyncQueueRepository {
  SyncQueueRepository(this._db, this._api);

  final AppDatabase _db;
  final ApiClient _api;

  /// A batch big enough to drain a normal afternoon's work in one request,
  /// small enough that a failure does not cost a large upload.
  static const _batchSize = 25;

  /// A v4-shaped identifier, generated here so the operation can be retried
  /// with the same id however many times it takes.
  static String newOperationId() {
    final random = Random.secure();
    String hex(int bytes) => List.generate(
          bytes,
          (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
        ).join();

    final variant = (random.nextInt(4) + 8).toRadixString(16);

    return '${hex(4)}-${hex(2)}-4${hex(2).substring(1)}-$variant${hex(2).substring(1)}-${hex(6)}';
  }

  Future<PendingWrite> enqueue({
    required String kind,
    required Map<String, dynamic> payload,
    String? subjectUlid,
    String? subjectLabel,
  }) async {
    final id = await _db.into(_db.syncQueue).insert(
          SyncQueueCompanion.insert(
            clientOperationId: newOperationId(),
            kind: Value(kind),
            // Kept for a person to read back, not for replay: the batch
            // endpoint is typed, so the queue stores intent rather than a
            // serialised HTTP call that could drift out of date.
            method: 'POST',
            endpoint: ApiPaths.syncBatch,
            payload: jsonEncode(payload),
            subjectUlid: Value(subjectUlid),
            subjectLabel: Value(subjectLabel),
            createdAt: DateTime.now(),
          ),
        );

    final row = await (_db.select(_db.syncQueue)..where((q) => q.id.equals(id))).getSingle();

    return PendingWrite.fromRow(row);
  }

  Future<List<PendingWrite>> all() async {
    final rows = await (_db.select(_db.syncQueue)
          ..orderBy([(q) => OrderingTerm(expression: q.id)]))
        .get();

    return rows.map(PendingWrite.fromRow).toList(growable: false);
  }

  Future<int> pendingCount() async {
    final rows = await (_db.select(_db.syncQueue)
          ..where((q) => q.status.equals('pending')))
        .get();

    return rows.length;
  }

  /// Forgets a write the person has decided not to keep.
  Future<void> discard(int id) =>
      (_db.delete(_db.syncQueue)..where((q) => q.id.equals(id))).go();

  /// Sends everything waiting, oldest first.
  ///
  /// Order matters: a father added before an event about him must reach the
  /// server in that order, which is why the whole batch goes in one request
  /// rather than in parallel.
  Future<SyncOutcome> flush() async {
    final waiting = await (_db.select(_db.syncQueue)
          ..where((q) => q.status.equals('pending'))
          ..orderBy([(q) => OrderingTerm(expression: q.id)])
          ..limit(_batchSize))
        .get();

    if (waiting.isEmpty) {
      return const SyncOutcome(applied: 0, failed: 0, stillWaiting: 0);
    }

    try {
      final envelope = await _api.post<List<dynamic>>(
        ApiPaths.syncBatch,
        body: {
          'operations': [
            for (final row in waiting)
              {
                'client_operation_id': row.clientOperationId,
                'kind': row.kind,
                'payload': jsonDecode(row.payload),
              },
          ],
        },
        parse: (data) => (data as List?) ?? const [],
      );

      return _settle(waiting, envelope.data ?? const []);
    } on ApiException catch (error) {
      if (error.isOffline) {
        // Nothing was attempted. The queue is untouched and the person is not
        // told that anything failed, because nothing did.
        return const SyncOutcome.offline();
      }

      await _recordAttempt(waiting, error.message);

      return SyncOutcome(
        applied: 0,
        failed: 0,
        stillWaiting: waiting.length,
      );
    }
  }

  Future<SyncOutcome> _settle(
    List<QueuedOperation> waiting,
    List<dynamic> results,
  ) async {
    final byId = {
      for (final result in results.whereType<Map>())
        result['client_operation_id'] as String? ?? '': result.cast<String, dynamic>(),
    };

    var applied = 0;
    var failed = 0;

    for (final row in waiting) {
      final result = byId[row.clientOperationId];

      if (result == null) continue;

      final status = result['status'] as String?;

      if (status == 'applied' || status == 'replayed') {
        await (_db.delete(_db.syncQueue)..where((q) => q.id.equals(row.id))).go();
        applied++;
        continue;
      }

      // Kept, not deleted: a rejected write is the person's work, and throwing
      // it away silently is how somebody loses an afternoon of it. They decide
      // whether to fix it or discard it.
      await (_db.update(_db.syncQueue)..where((q) => q.id.equals(row.id))).write(
        SyncQueueCompanion(
          status: const Value('rejected'),
          attempts: Value(row.attempts + 1),
          lastError: Value(result['message'] as String? ?? 'The server did not accept this.'),
        ),
      );
      failed++;
    }

    return SyncOutcome(
      applied: applied,
      failed: failed,
      stillWaiting: await pendingCount(),
    );
  }

  Future<void> _recordAttempt(List<QueuedOperation> waiting, String message) async {
    await _db.batch((b) {
      for (final row in waiting) {
        b.update(
          _db.syncQueue,
          SyncQueueCompanion(
            attempts: Value(row.attempts + 1),
            lastError: Value(message),
          ),
          where: (q) => q.id.equals(row.id),
        );
      }
    });
  }
}
