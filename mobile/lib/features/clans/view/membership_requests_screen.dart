import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/membership.dart';
import '../../../providers/onboarding_provider.dart';

/// Who is waiting to be let into a family you run.
///
/// A request grants nothing until it is answered, so a queue nobody can reach
/// from the app is a person who never gets in. This is that queue.
class MembershipRequestsScreen extends ConsumerStatefulWidget {
  const MembershipRequestsScreen({super.key});

  @override
  ConsumerState<MembershipRequestsScreen> createState() =>
      _MembershipRequestsScreenState();
}

class _MembershipRequestsScreenState
    extends ConsumerState<MembershipRequestsScreen> {
  String? _deciding;

  Future<void> _decide(Membership membership, {required bool approve}) async {
    setState(() => _deciding = membership.ulid);

    final messenger = ScaffoldMessenger.of(context);
    final who = membership.userName ?? 'They';

    try {
      await ref
          .read(onboardingRepositoryProvider)
          .decideMembership(membership.ulid, approve: approve);

      ref.invalidate(pendingMembershipsProvider);

      messenger.showSnackBar(
        SnackBar(
          content: Text(
            approve
                ? '$who is in ${membership.scopeName ?? 'the family'}.'
                : 'Declined.',
          ),
        ),
      );
    } catch (error) {
      // Said plainly and the row left where it is: a request that looks
      // answered and is not means somebody waits for nothing.
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            error is ApiException ? error.message : 'Could not answer that.',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _deciding = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final pending = ref.watch(pendingMembershipsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Join requests')),
      body: pending.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => _Retry(
          message: error is ApiException
              ? error.message
              : 'Could not read the requests.',
          onRetry: () => ref.invalidate(pendingMembershipsProvider),
        ),
        data: (rows) => rows.isEmpty
            ? _Empty(theme: theme)
            : RefreshIndicator(
                onRefresh: () async =>
                    ref.invalidate(pendingMembershipsProvider),
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                  itemCount: rows.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final row = rows[index];

                    return _RequestCard(
                      membership: row.membership,
                      scopeName: row.scope.name,
                      busy: _deciding == row.membership.ulid,
                      onApprove: () => _decide(row.membership, approve: true),
                      onDecline: () => _decide(row.membership, approve: false),
                    );
                  },
                ),
              ),
      ),
    );
  }
}

class _RequestCard extends StatelessWidget {
  const _RequestCard({
    required this.membership,
    required this.scopeName,
    required this.busy,
    required this.onApprove,
    required this.onDecline,
  });

  final Membership membership;
  final String scopeName;
  final bool busy;
  final VoidCallback onApprove;
  final VoidCallback onDecline;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              membership.userName ?? 'Someone',
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: 2),
            Text(
              'Asked to join $scopeName',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            if (membership.requestedAt != null) ...[
              const SizedBox(height: 2),
              Text(
                _asked(membership.requestedAt!),
                style: theme.textTheme.labelMedium,
              ),
            ],
            const SizedBox(height: 14),
            Row(
              children: [
                FilledButton(
                  onPressed: busy ? null : onApprove,
                  child: busy
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2.2),
                        )
                      : const Text('Approve'),
                ),
                const SizedBox(width: 10),
                TextButton(
                  onPressed: busy ? null : onDecline,
                  child: const Text('Decline'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  /// "Asked 3 days ago" — how long somebody has been waiting is the thing a
  /// reviewer is deciding about.
  static String _asked(DateTime when) {
    final days = DateTime.now().difference(when).inDays;

    return switch (days) {
      <= 0 => 'Asked today',
      1 => 'Asked yesterday',
      _ => 'Asked $days days ago',
    };
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.theme});

  final ThemeData theme;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.how_to_reg_outlined,
            size: 44,
            color: theme.colorScheme.onSurfaceVariant,
          ),
          const SizedBox(height: 16),
          Text('Nobody is waiting.', style: theme.textTheme.bodyLarge),
          const SizedBox(height: 8),
          Text(
            'People who ask to join a family you run appear here.',
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

class _Retry extends StatelessWidget {
  const _Retry({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 40),
          const SizedBox(height: 14),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 20),
          FilledButton(onPressed: onRetry, child: const Text('Try again')),
        ],
      ),
    ),
  );
}
