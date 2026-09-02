import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../repositories/sync_queue_repository.dart';
import 'app_providers.dart';

/// What the app is doing about the writes it has not yet delivered.
class SyncState {
  const SyncState({
    this.pending = const [],
    this.syncing = false,
    this.offline = false,
    this.lastOutcome,
  });

  final List<PendingWrite> pending;
  final bool syncing;

  /// Last known to be unreachable. Not a claim about the network — a claim
  /// about the server, which is the thing that actually matters. A phone with
  /// four bars on a hotel wifi captive portal is offline for our purposes.
  final bool offline;

  final SyncOutcome? lastOutcome;

  int get waitingCount => pending.where((w) => w.isPending).length;
  int get rejectedCount => pending.where((w) => w.isRejected).length;
  bool get hasWork => pending.isNotEmpty;

  SyncState copyWith({
    List<PendingWrite>? pending,
    bool? syncing,
    bool? offline,
    SyncOutcome? lastOutcome,
  }) =>
      SyncState(
        pending: pending ?? this.pending,
        syncing: syncing ?? this.syncing,
        offline: offline ?? this.offline,
        lastOutcome: lastOutcome ?? this.lastOutcome,
      );
}

/// Holds the offline queue and decides when to try again.
///
/// Reachability is learned from requests actually failing rather than asked of
/// the operating system: connectivity says whether a radio is on, not whether
/// this server can be reached. The connectivity stream is used only as a hint
/// that it is worth retrying.
class SyncController extends Notifier<SyncState> {
  StreamSubscription<List<ConnectivityResult>>? _connectivity;
  Timer? _retry;

  @override
  SyncState build() {
    ref.onDispose(() {
      _connectivity?.cancel();
      _retry?.cancel();
    });

    // A reconnection is a reason to try, not proof that it will work.
    _connectivity = Connectivity().onConnectivityChanged.listen((results) {
      final hasRadio = results.any((r) => r != ConnectivityResult.none);

      if (hasRadio && state.hasWork) {
        unawaited(sync());
      }
    });

    unawaited(refresh());

    return const SyncState();
  }

  SyncQueueRepository get _queue => ref.read(syncQueueProvider);

  Future<void> refresh() async {
    final pending = await _queue.all();

    state = state.copyWith(pending: pending);
  }

  /// Records a write to be sent when the server can be reached.
  Future<void> enqueue({
    required String kind,
    required Map<String, dynamic> payload,
    String? subjectUlid,
    String? subjectLabel,
  }) async {
    await _queue.enqueue(
      kind: kind,
      payload: payload,
      subjectUlid: subjectUlid,
      subjectLabel: subjectLabel,
    );

    await refresh();
  }

  /// Tries to deliver everything waiting.
  Future<SyncOutcome> sync() async {
    if (state.syncing) return const SyncOutcome(applied: 0, failed: 0, stillWaiting: 0);

    state = state.copyWith(syncing: true);

    try {
      final outcome = await _queue.flush();

      await refresh();

      state = state.copyWith(
        syncing: false,
        offline: outcome.unreachable,
        lastOutcome: outcome,
      );

      if (outcome.unreachable) _scheduleRetry();

      return outcome;
    } catch (error) {
      if (kDebugMode) debugPrint('sync failed: $error');

      state = state.copyWith(syncing: false);

      return const SyncOutcome.offline();
    }
  }

  /// Somebody has decided not to keep a write the server refused.
  Future<void> discard(int id) async {
    await _queue.discard(id);
    await refresh();
  }

  /// A slow, quiet retry so a phone left offline does not spend its battery
  /// asking a server it cannot reach.
  void _scheduleRetry() {
    _retry?.cancel();
    _retry = Timer(const Duration(minutes: 2), () {
      if (state.hasWork) unawaited(sync());
    });
  }
}

final syncControllerProvider =
    NotifierProvider<SyncController, SyncState>(SyncController.new);
