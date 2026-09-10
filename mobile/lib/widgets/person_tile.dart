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
    this.label,
    this.trailing,
  });

  final PersonSummary person;
  final bool selected;
  final VoidCallback? onTap;

  /// What this person is to the family being read — "1st son", "2nd daughter".
  /// A name alone does not say where somebody stands among their siblings, and
  /// in most families that is the first thing anybody wants to know.
  final String? label;

  /// Actions belonging to this row rather than to the person.
  final Widget? trailing;

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
                          const Icon(
                            Icons.verified,
                            size: 17,
                            color: AppTheme.verified,
                          ),
                        ],
                        if (person.hasOpenDispute) ...[
                          const SizedBox(width: 6),
                          const Icon(
                            Icons.help_outline,
                            size: 17,
                            color: AppTheme.disputed,
                          ),
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
                    if (label != null)
                      Text(
                        label!,
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: theme.colorScheme.primary,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    const SizedBox(height: 2),

                    // Parents where there are no dates. A column of names all
                    // reading "No dates recorded" cannot be told apart, and in
                    // a family with a dozen Thawngs the parents are what
                    // distinguishes them. Dates win where they exist: they are
                    // shorter and they place somebody in time.
                    if (person.lifespan == null &&
                        (person.fatherName != null ||
                            person.motherName != null))
                      for (final line in [
                        if (person.fatherName != null)
                          'Father: ${person.fatherName}',
                        if (person.motherName != null)
                          'Mother: ${person.motherName}',
                      ])
                        Text(
                          line,
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        )
                    else
                      Text(
                        person.dateLine,
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
              if (selected)
                Icon(Icons.check_circle, color: theme.colorScheme.primary),
              ?trailing,
            ],
          ),
        ),
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
