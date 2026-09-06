import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/clan_registration.dart';
import '../../../providers/clan_provider.dart';
import '../../../routing/app_router.dart';

/// Requests to start a clan — the ones you made, and the ones waiting on you.
///
/// Both in one place because they are the same object at different stages, and
/// somebody who runs a tribe has usually also asked for something themselves.
class ClanRegistrationsScreen extends ConsumerWidget {
  const ClanRegistrationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final registrations = ref.watch(clanRegistrationsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Clans')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push(Routes.startClan),
        icon: const Icon(Icons.add),
        label: const Text('Start a clan'),
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(clanRegistrationsProvider),
        child: registrations.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => _Message(
            icon: Icons.cloud_off_outlined,
            title: 'Could not read your requests',
            body: error is ApiException
                ? error.message
                : 'Try again in a moment.',
          ),
          data: (all) {
            final waiting = all.where((r) => r.canDecide).toList();
            final mine = all.where((r) => !r.canDecide).toList();

            if (all.isEmpty) {
              return const _Message(
                icon: Icons.account_tree_outlined,
                title: 'No clan requests yet',
                body:
                    'A clan groups the families descended from one ancestor. '
                    'Ask to start one and whoever runs the tribe will decide.',
              );
            }

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
              children: [
                if (waiting.isNotEmpty) ...[
                  const _Heading('Waiting on you'),
                  for (final r in waiting)
                    _RegistrationCard(registration: r, showRequester: true),
                  const SizedBox(height: 20),
                ],
                if (mine.isNotEmpty) ...[
                  const _Heading('Your requests'),
                  for (final r in mine) _RegistrationCard(registration: r),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

class _Heading extends StatelessWidget {
  const _Heading(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(4, 4, 4, 10),
    child: Text(text, style: Theme.of(context).textTheme.titleMedium),
  );
}

class _RegistrationCard extends ConsumerStatefulWidget {
  const _RegistrationCard({
    required this.registration,
    this.showRequester = false,
  });

  final ClanRegistration registration;
  final bool showRequester;

  @override
  ConsumerState<_RegistrationCard> createState() => _RegistrationCardState();
}

class _RegistrationCardState extends ConsumerState<_RegistrationCard> {
  bool _busy = false;

  /// Every decision goes through here so one place owns the spinner, the
  /// refresh and the failure message — three screens' worth of near-identical
  /// code otherwise, differing in exactly the way that hides a bug.
  Future<void> _run(Future<void> Function() work, String done) async {
    setState(() => _busy = true);

    try {
      await work();
      ref.invalidate(clanRegistrationsProvider);
      // A decision changes what the decider can reach, and an approval hands
      // a whole clan to somebody.
      ref.invalidate(administeredScopesProvider);

      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(done)));
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Approving creates a clan and hands it to somebody. Saying so beats
  /// "Are you sure?" about consequences that are not on the screen.
  Future<void> _confirmApprove() async {
    final registration = widget.registration;
    final who = registration.requesterName ?? 'the person who asked';

    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Approve ${registration.name}?'),
        content: Text(
          'This creates the clan and makes $who its administrator. '
          'They will be able to appoint others and approve members.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Not yet'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Approve'),
          ),
        ],
      ),
    );

    if (ok ?? false) {
      await _run(
        () => ref.read(clanRepositoryProvider).approve(registration.ulid),
        '${registration.name} was created.',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final r = widget.registration;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(r.name, style: theme.textTheme.titleMedium),
                ),
                _StatusChip(status: r.status),
              ],
            ),
            if (r.tribeName != null ||
                (widget.showRequester && r.requesterName != null))
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  [
                    if (widget.showRequester && r.requesterName != null)
                      'Asked by ${r.requesterName}',
                    if (r.tribeName != null) 'in ${r.tribeName}',
                  ].join(' '),
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            if (r.statement != null && r.statement!.isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(r.statement!, style: theme.textTheme.bodyMedium),
            ],
            // A refusal with no reason is the thing people come back and ask
            // about, so it is shown next to the status rather than lost.
            if (r.decisionNote != null && r.decisionNote!.isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                'Note: ${r.decisionNote}',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
            if (_busy) ...[
              const SizedBox(height: 12),
              const LinearProgressIndicator(),
            ] else if (r.canDecide) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => _run(
                        () => ref.read(clanRepositoryProvider).reject(r.ulid),
                        'Refused.',
                      ),
                      child: const Text('Refuse'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton(
                      onPressed: _confirmApprove,
                      child: const Text('Approve'),
                    ),
                  ),
                ],
              ),
            ] else if (r.canWithdraw) ...[
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => _run(
                  () => ref.read(clanRepositoryProvider).withdraw(r.ulid),
                  'Request withdrawn.',
                ),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(44),
                ),
                child: const Text('Withdraw'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    final (label, colour) = switch (status) {
      'approved' => ('Approved', scheme.primary),
      'rejected' => ('Refused', scheme.error),
      'withdrawn' => ('Withdrawn', scheme.onSurfaceVariant),
      _ => ('Waiting', scheme.tertiary),
    };

    return Chip(
      label: Text(label),
      labelStyle: Theme.of(
        context,
      ).textTheme.labelMedium?.copyWith(color: colour),
      visualDensity: VisualDensity.compact,
      side: BorderSide(color: colour.withValues(alpha: 0.4)),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    // A list, not a Center: RefreshIndicator needs something scrollable or
    // pulling to retry does nothing at all — which is exactly what somebody
    // does first when a screen says it could not load.
    return ListView(
      padding: const EdgeInsets.fromLTRB(32, 80, 32, 32),
      children: [
        Icon(icon, size: 44, color: theme.colorScheme.onSurfaceVariant),
        const SizedBox(height: 16),
        Text(
          title,
          textAlign: TextAlign.center,
          style: theme.textTheme.titleMedium,
        ),
        const SizedBox(height: 8),
        Text(
          body,
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyMedium?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
  }
}
