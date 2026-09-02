import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../providers/auth_provider.dart';
import '../../../providers/sync_provider.dart';

/// Says what has not reached the server yet.
///
/// Shown wherever somebody might be about to trust what is on screen. A tree
/// assembled from the device with three unsent corrections behind it looks
/// exactly like a tree fetched a second ago, and the difference matters.
class SyncBanner extends ConsumerWidget {
  const SyncBanner({super.key, this.onTap});

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sync = ref.watch(syncControllerProvider);
    final theme = Theme.of(context);

    final auth = ref.watch(authProvider);
    final openedOffline = auth is AuthSignedIn && auth.offline;

    if (!sync.hasWork) {
      // Nothing queued, but the app was opened on a remembered account. Saying
      // so is the difference between looking deliberate and looking broken.
      return openedOffline
          ? _Bar(
              icon: Icons.cloud_off,
              tone: theme.colorScheme.onSurfaceVariant,
              message: 'Working offline — showing what is saved on this device',
              onTap: onTap,
            )
          : const SizedBox.shrink();
    }

    final rejected = sync.rejectedCount;
    final waiting = sync.waitingCount;

    // A refusal needs a person's attention; things merely waiting do not.
    final needsAttention = rejected > 0;

    final tone = needsAttention ? theme.colorScheme.error : theme.colorScheme.primary;

    return _Bar(
      icon: needsAttention ? Icons.error_outline : Icons.cloud_upload_outlined,
      tone: tone,
      message: _message(sync, waiting, rejected),
      busy: sync.syncing,
      onTap: onTap,
    );
  }

  String _message(SyncState sync, int waiting, int rejected) {
    if (sync.syncing) return 'Sending your changes…';

    if (rejected > 0) {
      return rejected == 1
          ? '1 change was not accepted'
          : '$rejected changes were not accepted';
    }

    final what = waiting == 1 ? '1 change' : '$waiting changes';

    return sync.offline
        ? '$what saved here, waiting for a connection'
        : '$what waiting to be sent';
  }
}

class _Bar extends StatelessWidget {
  const _Bar({
    required this.icon,
    required this.tone,
    required this.message,
    this.busy = false,
    this.onTap,
  });

  final IconData icon;
  final Color tone;
  final String message;
  final bool busy;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Material(
      color: tone.withValues(alpha: 0.10),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            children: [
              if (busy)
                const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              else
                Icon(icon, size: 18, color: tone),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  message,
                  style: theme.textTheme.bodyMedium?.copyWith(color: tone),
                ),
              ),
              if (onTap != null) Icon(Icons.chevron_right, size: 18, color: tone),
            ],
          ),
        ),
      ),
    );
  }
}
