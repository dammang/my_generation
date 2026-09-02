import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/person_summary.dart';
import '../../../models/tree_graph.dart';

/// One person on the chart.
///
/// A real widget rather than something painted, so it can be tapped, focused,
/// and read aloud. The semantics label is written out in full because a screen
/// reader user gets no benefit from a layout that reads "Thawng Dam, 1920,
/// 1998, tick".
class TreePersonCard extends StatelessWidget {
  const TreePersonCard({
    super.key,
    required this.person,
    required this.isFocus,
    required this.expandable,
    this.onTap,
    this.onExpand,
  });

  final PersonSummary person;
  final bool isFocus;
  final Expandable expandable;
  final VoidCallback? onTap;
  final void Function(bool ancestors)? onExpand;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    // A person the viewer may not see still occupies their position — hiding
    // the node would misrepresent everybody else's lineage.
    if (person.placeholder) return _placeholder(theme);

    return Semantics(
      button: true,
      label: _semanticsLabel,
      child: Material(
        color: isFocus ? scheme.primaryContainer : scheme.surface,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: isFocus ? scheme.primary : scheme.outlineVariant,
                width: isFocus ? 2 : 1,
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _Avatar(person: person),
                    if (person.isVerified) ...[
                      const SizedBox(width: 4),
                      const Icon(Icons.verified, size: 14, color: AppTheme.verified),
                    ],
                    if (person.hasOpenDispute) ...[
                      const SizedBox(width: 4),
                      const Icon(Icons.help_outline, size: 14, color: AppTheme.disputed),
                    ],
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  person.displayName,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.labelLarge?.copyWith(height: 1.15),
                ),
                const SizedBox(height: 2),
                Text(
                  person.lifespan ?? (person.redacted ? 'Dates withheld' : '—'),
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: person.redacted ? AppTheme.redacted : scheme.onSurfaceVariant,
                    fontStyle: person.redacted ? FontStyle.italic : null,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _placeholder(ThemeData theme) => Semantics(
        label: 'A person you do not have permission to see',
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: theme.colorScheme.outlineVariant,
              style: BorderStyle.solid,
            ),
          ),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.lock_outline, size: 20, color: theme.colorScheme.onSurfaceVariant),
                const SizedBox(height: 6),
                Text('Private', style: theme.textTheme.labelMedium),
              ],
            ),
          ),
        ),
      );

  String get _semanticsLabel {
    final parts = <String>[person.displayName];

    if (person.lifespan != null) parts.add(person.lifespan!);
    if (person.isVerified) parts.add('verified');
    if (person.hasOpenDispute) parts.add('has a disputed fact');
    if (person.redacted) parts.add('some details are withheld');
    if (isFocus) parts.add('currently centred');
    if (expandable.parents > 0) parts.add('${expandable.parents} more parents not shown');
    if (expandable.children > 0) parts.add('${expandable.children} more children not shown');

    return parts.join(', ');
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.person});

  final PersonSummary person;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return CircleAvatar(
      radius: 14,
      backgroundColor: theme.colorScheme.surfaceContainerHighest,
      backgroundImage: person.photoUrl == null ? null : NetworkImage(person.photoUrl!),
      child: person.photoUrl != null
          ? null
          : Text(
              _initials(person.displayName),
              style: theme.textTheme.labelMedium?.copyWith(fontSize: 11),
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

/// The "+N more" affordance on a node whose family continues past the edge of
/// what was fetched.
class ExpandChip extends StatelessWidget {
  const ExpandChip({
    super.key,
    required this.count,
    required this.ancestors,
    required this.onTap,
  });

  final int count;
  final bool ancestors;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Semantics(
      button: true,
      label: ancestors ? 'Show $count more ancestors' : 'Show $count more children',
      child: Material(
        color: theme.colorScheme.secondaryContainer,
        shape: const StadiumBorder(),
        child: InkWell(
          onTap: onTap,
          customBorder: const StadiumBorder(),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  ancestors ? Icons.keyboard_arrow_up : Icons.keyboard_arrow_down,
                  size: 16,
                  color: theme.colorScheme.onSecondaryContainer,
                ),
                const SizedBox(width: 2),
                Text(
                  '$count',
                  style: theme.textTheme.labelMedium?.copyWith(
                    color: theme.colorScheme.onSecondaryContainer,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
