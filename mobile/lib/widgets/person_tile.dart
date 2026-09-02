import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';
import '../models/person_summary.dart';

/// One person in a list.
///
/// Shows what the server permitted and nothing more: a redacted record says so
/// rather than showing a blank where a date would be, because an unexplained
/// gap reads as missing data when it is actually withheld.
class PersonTile extends StatelessWidget {
  const PersonTile({
    super.key,
    required this.person,
    this.selected = false,
    this.onTap,
  });

  final PersonSummary person;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      color: selected ? theme.colorScheme.primaryContainer : null,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              CircleAvatar(
                radius: 24,
                backgroundColor: theme.colorScheme.surfaceContainerHighest,
                child: Text(
                  _initials(person.displayName),
                  style: theme.textTheme.titleMedium,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            person.displayName,
                            style: theme.textTheme.titleMedium,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (person.isVerified) ...[
                          const SizedBox(width: 6),
                          const Icon(Icons.verified, size: 17, color: AppTheme.verified),
                        ],
                        if (person.hasOpenDispute) ...[
                          const SizedBox(width: 6),
                          const Icon(Icons.help_outline, size: 17, color: AppTheme.disputed),
                        ],
                      ],
                    ),
                    if (person.nativeName != null)
                      Text(
                        person.nativeName!,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    const SizedBox(height: 2),
                    Text(
                      person.lifespan ??
                          (person.redacted ? 'Dates not shown' : 'No dates recorded'),
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: person.redacted
                            ? AppTheme.redacted
                            : theme.colorScheme.onSurfaceVariant,
                        fontStyle: person.redacted ? FontStyle.italic : null,
                      ),
                    ),
                  ],
                ),
              ),
              if (selected) Icon(Icons.check_circle, color: theme.colorScheme.primary),
            ],
          ),
        ),
      ),
    );
  }

  static String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();

    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.characters.take(1).toString().toUpperCase();

    return (parts.first.characters.take(1).toString() +
            parts.last.characters.take(1).toString())
        .toUpperCase();
  }
}
