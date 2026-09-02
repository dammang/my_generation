import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/person_detail.dart';

/// The top of a profile: who this is, when they lived, and how sure we are.
///
/// Verification and dispute are shown next to the name rather than buried in a
/// tab, because "this is contested" changes how everything below should be read.
class PersonHeader extends StatelessWidget {
  const PersonHeader({super.key, required this.detail});

  final PersonDetail detail;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final person = detail.summary;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 34,
                backgroundColor: theme.colorScheme.surfaceContainerHighest,
                backgroundImage: person.photoUrl == null
                    ? null
                    : NetworkImage(person.photoUrl!),
                child: person.photoUrl != null
                    ? null
                    : Text(
                        _initials(person.displayName),
                        style: theme.textTheme.titleLarge,
                      ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      person.displayName,
                      style: theme.textTheme.headlineSmall,
                    ),
                    if (person.nativeName != null)
                      Text(
                        person.nativeName!,
                        style: theme.textTheme.titleMedium?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    const SizedBox(height: 6),
                    Text(
                      person.lifespan ??
                          (person.redacted
                              ? 'Dates not shown'
                              : 'No dates recorded'),
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        fontStyle: person.redacted ? FontStyle.italic : null,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (person.isVerified)
                const _Badge(
                  icon: Icons.verified,
                  label: 'Verified',
                  color: AppTheme.verified,
                ),
              if (person.hasOpenDispute)
                const _Badge(
                  icon: Icons.help_outline,
                  label: 'Disputed',
                  color: AppTheme.disputed,
                ),
              if (person.isLiving)
                const _Badge(icon: Icons.person_outline, label: 'Living'),
              if (person.generationLabel != null)
                _Badge(
                  icon: Icons.stairs_outlined,
                  label: person.generationLabel!,
                ),
              if (detail.clanName != null)
                _Badge(icon: Icons.groups_outlined, label: detail.clanName!),
              if (detail.branchName != null)
                _Badge(
                  icon: Icons.account_tree_outlined,
                  label: detail.branchName!,
                ),
            ],
          ),
          if (person.redacted) ...[
            const SizedBox(height: 14),
            _Notice(
              icon: Icons.lock_outline,
              // Somebody is entitled to know the record is fuller than what
              // they can see. Showing nothing would read as nothing recorded.
              text:
                  'Some details are hidden by this person’s privacy settings.',
            ),
          ],
          if (detail.isMerged) ...[
            const SizedBox(height: 14),
            _Notice(
              icon: Icons.merge_type,
              text:
                  'This record was merged into another. You are seeing the '
                  'record it was merged into.',
            ),
          ],
        ],
      ),
    );
  }

  static String _initials(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((p) => p.isNotEmpty)
        .toList();

    if (parts.isEmpty) return '?';
    if (parts.length == 1) {
      return parts.first.characters.take(1).toString().toUpperCase();
    }

    return (parts.first.characters.take(1).toString() +
            parts.last.characters.take(1).toString())
        .toUpperCase();
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.icon, required this.label, this.color});

  final IconData icon;
  final String label;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tint = color ?? theme.colorScheme.onSurfaceVariant;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: tint),
          const SizedBox(width: 5),
          Text(
            label,
            style: theme.textTheme.labelMedium?.copyWith(color: tint),
          ),
        ],
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
