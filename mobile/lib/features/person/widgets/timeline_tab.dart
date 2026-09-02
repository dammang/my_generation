import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/person_event.dart';

/// A life, in order.
///
/// Three states, not two: events, nothing recorded, and withheld. An empty
/// timeline invites a contribution; a withheld one must not, because inviting
/// somebody to fill in a life they are not allowed to see is a lie about why
/// the screen is blank.
class TimelineTab extends StatelessWidget {
  const TimelineTab({
    super.key,
    required this.timeline,
    required this.onAddEvent,
  });

  final Timeline timeline;
  final VoidCallback onAddEvent;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (timeline.unavailableOffline) {
      return _Empty(
        icon: Icons.cloud_off,
        title: 'Not saved on this device',
        message: 'Life events are not kept offline. Connect to see them.',
      );
    }

    if (timeline.withheld) {
      return _Empty(
        icon: Icons.lock_outline,
        title: 'This life story is private',
        message:
            'You do not have permission to see events recorded for this '
            'person.',
      );
    }

    if (timeline.isEmpty) {
      return _Empty(
        icon: Icons.history_edu_outlined,
        title: 'Nothing recorded yet',
        message:
            'Births, migrations, service, marriages — the things worth '
            'remembering about this person.',
        action: FilledButton.icon(
          onPressed: onAddEvent,
          icon: const Icon(Icons.add),
          label: const Text('Add an event'),
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
      itemCount: timeline.events.length,
      itemBuilder: (context, index) {
        final event = timeline.events[index];

        return _Entry(
          event: event,
          isFirst: index == 0,
          isLast: index == timeline.events.length - 1,
          theme: theme,
        );
      },
    );
  }
}

class _Entry extends StatelessWidget {
  const _Entry({
    required this.event,
    required this.isFirst,
    required this.isLast,
    required this.theme,
  });

  final PersonEvent event;
  final bool isFirst;
  final bool isLast;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 62,
            child: Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                // The year alone in the gutter; the fuller wording sits with
                // the entry, where there is room to say "abt." honestly.
                event.year?.toString() ?? '—',
                textAlign: TextAlign.right,
                style: theme.textTheme.labelLarge?.copyWith(
                  fontFeatures: const [FontFeature.tabularFigures()],
                  color: event.year == null
                      ? theme.colorScheme.onSurfaceVariant
                      : theme.colorScheme.onSurface,
                ),
              ),
            ),
          ),
          _Rail(isFirst: isFirst, isLast: isLast, theme: theme, event: event),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(4, 8, 0, 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: Text(
                          event.heading,
                          style: theme.textTheme.titleSmall,
                        ),
                      ),
                      if (event.isVerified) ...[
                        const SizedBox(width: 6),
                        const Icon(
                          Icons.verified,
                          size: 15,
                          color: AppTheme.verified,
                        ),
                      ],
                    ],
                  ),
                  if (event.dateDetail != null)
                    Text(
                      event.dateDetail!,
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        fontStyle: event.isUncertain ? FontStyle.italic : null,
                      ),
                    ),
                  if (event.placeLine != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Row(
                        children: [
                          Icon(
                            event.isMigration
                                ? Icons.route_outlined
                                : Icons.place_outlined,
                            size: 14,
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                          const SizedBox(width: 4),
                          Flexible(
                            child: Text(
                              event.placeLine!,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  if (event.description != null &&
                      event.description!.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text(
                        event.description!,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// The spine of the timeline: a line with a dot per entry.
class _Rail extends StatelessWidget {
  const _Rail({
    required this.isFirst,
    required this.isLast,
    required this.theme,
    required this.event,
  });

  final bool isFirst;
  final bool isLast;
  final ThemeData theme;
  final PersonEvent event;

  @override
  Widget build(BuildContext context) {
    final line = theme.colorScheme.outlineVariant;

    return SizedBox(
      width: 34,
      child: Column(
        children: [
          SizedBox(
            height: 14,
            child: isFirst
                ? const SizedBox.shrink()
                : Center(child: Container(width: 2, color: line)),
          ),
          Container(
            width: 11,
            height: 11,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              // A hollow dot for a date nobody is sure of. The rail then shows
              // certainty at a glance, without a legend.
              color: event.isUncertain
                  ? theme.colorScheme.surface
                  : theme.colorScheme.primary,
              border: Border.all(color: theme.colorScheme.primary, width: 2),
            ),
          ),
          Expanded(
            child: isLast
                ? const SizedBox.shrink()
                : Center(child: Container(width: 2, color: line)),
          ),
        ],
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(36),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 44, color: theme.colorScheme.onSurfaceVariant),
            const SizedBox(height: 14),
            Text(title, style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            if (action != null) ...[const SizedBox(height: 20), action!],
          ],
        ),
      ),
    );
  }
}
