import 'package:flutter/material.dart';

import '../../../models/dispute.dart' as models;
import '../../../models/revision.dart';

/// What this record used to say, and who changed it.
///
/// History is the answer to "that is not what my grandmother told me". Showing
/// only the current value presents every correction as though it had always
/// been so, which is exactly what makes a shared archive feel untrustworthy.
class HistoryTab extends StatelessWidget {
  const HistoryTab({
    super.key,
    required this.history,
    required this.disputes,
    required this.onRaiseDispute,
  });

  final History history;
  final List<models.Dispute> disputes;
  final VoidCallback onRaiseDispute;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (history.withheld) {
      return _Message(
        icon: Icons.lock_outline,
        title: 'History is private',
        message: 'You do not have permission to see how this record has '
            'changed.',
      );
    }

    final open = disputes.where((d) => d.isOpen).toList(growable: false);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
      children: [
        for (final dispute in open) _DisputeCard(dispute: dispute),
        if (history.isEmpty)
          _Message(
            icon: Icons.history,
            title: 'No changes recorded',
            message: 'Nothing about this record has been corrected since it '
                'was added.',
          )
        else ...[
          Text(
            'CHANGES',
            style: theme.textTheme.labelLarge?.copyWith(
              letterSpacing: 0.8,
              color: theme.colorScheme.primary,
            ),
          ),
          const SizedBox(height: 8),
          for (final entry in history.entries) _RevisionRow(entry: entry),
        ],
        const SizedBox(height: 20),
        OutlinedButton.icon(
          onPressed: onRaiseDispute,
          icon: const Icon(Icons.flag_outlined, size: 18),
          label: const Text('Something here is wrong'),
        ),
      ],
    );
  }
}

class _RevisionRow extends StatelessWidget {
  const _RevisionRow({required this.entry});

  final RevisionEntry entry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(entry.label, style: theme.textTheme.titleSmall)),
              if (entry.viaChangeRequest)
                Tooltip(
                  // Worth distinguishing: the value was agreed, not just typed.
                  message: 'Agreed through review',
                  child: Icon(
                    Icons.how_to_reg,
                    size: 16,
                    color: theme.colorScheme.primary,
                  ),
                ),
            ],
          ),
          if (entry.isFieldChange)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Row(
                children: [
                  Flexible(
                    child: Text(
                      entry.beforeText,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        decoration: TextDecoration.lineThrough,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 6),
                    child: Icon(Icons.arrow_forward, size: 14),
                  ),
                  Flexible(
                    child: Text(entry.afterText, style: theme.textTheme.bodyMedium),
                  ),
                ],
              ),
            ),
          if (entry.reason != null && entry.reason!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                entry.reason!,
                style: theme.textTheme.bodySmall?.copyWith(
                  fontStyle: FontStyle.italic,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(
              [
                if (entry.changedByName != null) entry.changedByName!,
                if (entry.at != null) _when(entry.at!),
                if (entry.sourceTitle != null) 'source: ${entry.sourceTitle}',
              ].join(' · '),
              style: theme.textTheme.labelSmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        ],
      ),
    );
  }

  static String _when(DateTime at) {
    final local = at.toLocal();

    String two(int n) => n.toString().padLeft(2, '0');

    return '${local.year}-${two(local.month)}-${two(local.day)}';
  }
}

class _DisputeCard extends StatelessWidget {
  const _DisputeCard({required this.dispute});

  final models.Dispute dispute;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      color: theme.colorScheme.errorContainer.withValues(alpha: 0.35),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.help_outline, size: 18, color: theme.colorScheme.error),
                const SizedBox(width: 8),
                Text(
                  '${dispute.label} is disputed',
                  style: theme.textTheme.titleSmall,
                ),
              ],
            ),
            const SizedBox(height: 10),
            // Every version stays. Resolving records which was accepted, and
            // does not delete the others.
            for (final claim in dispute.claims)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      claim.accepted ? Icons.check_circle : Icons.circle_outlined,
                      size: 16,
                      color: claim.accepted
                          ? theme.colorScheme.primary
                          : theme.colorScheme.onSurfaceVariant,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(claim.value, style: theme.textTheme.bodyLarge),
                          if (claim.rationale != null)
                            Text(
                              claim.rationale!,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          if (claim.claimedByName != null)
                            Text(
                              'said by ${claim.claimedByName}',
                              style: theme.textTheme.labelSmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.title, required this.message});

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 20),
      child: Column(
        children: [
          Icon(icon, size: 40, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(height: 12),
          Text(title, style: theme.textTheme.titleMedium),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }
}
