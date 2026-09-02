import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../providers/sync_provider.dart';
import '../../../repositories/sync_queue_repository.dart';

/// Everything written on this device that the server has not accepted.
///
/// A refused write is kept rather than discarded. It is somebody's work — an
/// afternoon of it, sometimes — and deleting it quietly because the server said
/// no is the fastest way to lose a contributor.
class PendingChangesScreen extends ConsumerWidget {
  const PendingChangesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sync = ref.watch(syncControllerProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Waiting to send'),
        actions: [
          if (sync.hasWork)
            IconButton(
              tooltip: 'Try now',
              icon: const Icon(Icons.refresh),
              onPressed: sync.syncing
                  ? null
                  : () => ref.read(syncControllerProvider.notifier).sync(),
            ),
        ],
      ),
      body: sync.pending.isEmpty
          ? _Empty(offline: sync.offline)
          : ListView(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 32),
              children: [
                if (sync.offline)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(
                      'The server cannot be reached. Nothing here is lost — it '
                      'will be sent as soon as it can be.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                for (final write in sync.pending)
                  _PendingCard(
                    write: write,
                    onDiscard: () =>
                        ref.read(syncControllerProvider.notifier).discard(write.id),
                  ),
              ],
            ),
    );
  }
}

class _PendingCard extends StatelessWidget {
  const _PendingCard({required this.write, required this.onDiscard});

  final PendingWrite write;
  final VoidCallback onDiscard;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final refused = write.isRejected;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  refused ? Icons.error_outline : Icons.schedule,
                  size: 18,
                  color: refused
                      ? theme.colorScheme.error
                      : theme.colorScheme.onSurfaceVariant,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(write.describe(), style: theme.textTheme.titleSmall),
                ),
              ],
            ),
            if (refused && write.lastError != null) ...[
              const SizedBox(height: 10),
              Text(
                write.lastError!,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.error,
                ),
              ),
              const SizedBox(height: 6),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: onDiscard,
                  child: const Text('Discard it'),
                ),
              ),
            ] else
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  write.attempts == 0
                      ? 'Waiting to be sent'
                      : 'Tried ${write.attempts} ${write.attempts == 1 ? 'time' : 'times'}',
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.offline});

  final bool offline;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(36),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              offline ? Icons.cloud_off : Icons.cloud_done_outlined,
              size: 44,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 14),
            Text(
              offline ? 'Nothing waiting' : 'Everything is sent',
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Text(
              'Changes you make without a connection are kept here until they '
              'reach the server.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
