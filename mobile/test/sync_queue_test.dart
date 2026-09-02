import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/database/app_database.dart';
import 'package:my_generation/repositories/sync_queue_repository.dart';

import 'support/fake_api.dart';

const _batch = 'POST /api/v1/sync/batch';

Map<String, dynamic> _results(List<Map<String, dynamic>> data) => {
      'success': true,
      'data': data,
      'meta': const <String, dynamic>{},
      'warnings': const <dynamic>[],
    };

void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase(NativeDatabase.memory()));
  tearDown(() => db.close());

  SyncQueueRepository queueWith(FakeAdapter adapter) =>
      SyncQueueRepository(db, fakeApiClient(adapter));

  Future<void> enqueueFather(SyncQueueRepository queue) => queue.enqueue(
        kind: 'add_relative',
        subjectUlid: 'ANCHOR',
        subjectLabel: 'Ngul Muan',
        payload: const {
          'anchor_ulid': 'ANCHOR',
          'relation': 'father',
          'person': {'first_name': 'Thawng'},
        },
      ).then((_) {});

  test('an unreachable server leaves the queue untouched', () async {
    // Nothing was attempted, so nothing failed. Telling somebody their work
    // was rejected because their train went into a tunnel is a lie.
    final adapter = FakeAdapter({});
    final queue = queueWith(adapter);

    await enqueueFather(queue);

    final outcome = await queue.flush();

    expect(outcome.unreachable, isTrue);
    expect(outcome.didSomething, isFalse);
    expect(await queue.pendingCount(), 1);
  });

  test('an applied operation leaves the queue', () async {
    final queue = queueWith(FakeAdapter({}));
    await enqueueFather(queue);

    final id = (await queue.all()).single.clientOperationId;

    final adapter = FakeAdapter({
      _batch: [
        FakeReply(200, _results([
          {'client_operation_id': id, 'status': 'applied', 'code': 200},
        ])),
      ],
    });

    final outcome = await SyncQueueRepository(db, fakeApiClient(adapter)).flush();

    expect(outcome.applied, 1);
    expect(await queue.pendingCount(), 0);
  });

  test('a replayed operation also leaves the queue', () async {
    // The server has seen this id before, so the work is done. Keeping it
    // queued would make the phone ask forever.
    final queue = queueWith(FakeAdapter({}));
    await enqueueFather(queue);

    final id = (await queue.all()).single.clientOperationId;

    final adapter = FakeAdapter({
      _batch: [
        FakeReply(200, _results([
          {'client_operation_id': id, 'status': 'replayed', 'code': 200},
        ])),
      ],
    });

    await SyncQueueRepository(db, fakeApiClient(adapter)).flush();

    expect(await queue.pendingCount(), 0);
  });

  test('a refused operation is kept, with what went wrong', () async {
    // Somebody's work. Deleting it silently because the server said no is the
    // fastest way to lose a contributor.
    final queue = queueWith(FakeAdapter({}));
    await enqueueFather(queue);

    final id = (await queue.all()).single.clientOperationId;

    final adapter = FakeAdapter({
      _batch: [
        FakeReply(200, _results([
          {
            'client_operation_id': id,
            'status': 'failed',
            'code': 403,
            'message': 'You may not add relatives to this person.',
          },
        ])),
      ],
    });

    final outcome = await SyncQueueRepository(db, fakeApiClient(adapter)).flush();

    expect(outcome.failed, 1);

    final kept = (await queue.all()).single;

    expect(kept.isRejected, isTrue);
    expect(kept.lastError, 'You may not add relatives to this person.');
    expect(await queue.pendingCount(), 0);
  });

  test('operations are sent oldest first', () async {
    // A father added before an event about him must reach the server in that
    // order, or the event names somebody who does not exist yet.
    final queue = queueWith(FakeAdapter({}));

    await enqueueFather(queue);
    await queue.enqueue(
      kind: 'add_event',
      subjectLabel: 'Thawng',
      payload: const {'person_ulid': 'NEW', 'event_type': 'migration'},
    );

    final adapter = FakeAdapter({
      _batch: [FakeReply(200, _results(const []))],
    });

    await SyncQueueRepository(db, fakeApiClient(adapter)).flush();

    final sent = (adapter.received.single.data['operations'] as List)
        .map((op) => op['kind'] as String)
        .toList();

    expect(sent, ['add_relative', 'add_event']);
  });

  test('describes waiting work in a person\'s words', () async {
    final queue = queueWith(FakeAdapter({}));
    await enqueueFather(queue);

    // A row of json helps nobody decide whether to keep or discard it.
    expect((await queue.all()).single.describe(), 'A father for Ngul Muan');
  });

  test('a discarded operation is gone', () async {
    final queue = queueWith(FakeAdapter({}));
    await enqueueFather(queue);

    await queue.discard((await queue.all()).single.id);

    expect(await queue.all(), isEmpty);
  });

  test('operation ids are unique and correctly shaped', () {
    final ids = {for (var i = 0; i < 200; i++) SyncQueueRepository.newOperationId()};

    expect(ids.length, 200);

    // The server validates these as uuids.
    final uuid = RegExp(
      r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
    );

    for (final id in ids) {
      expect(uuid.hasMatch(id), isTrue, reason: id);
    }
  });
}
