import 'package:flutter/material.dart';

import '../../../models/story.dart';

/// Stories written about one person.
///
/// The card shows the summary, never the body: the body is fetched only when
/// somebody opens the story, so a list of twenty is twenty summaries rather
/// than twenty essays pulled over a phone connection.
class StoriesTab extends StatelessWidget {
  const StoriesTab({super.key, required this.stories, required this.onOpen, required this.onWrite});

  final List<Story> stories;
  final void Function(Story story) onOpen;
  final VoidCallback onWrite;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (stories.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(36),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                Icons.auto_stories_outlined,
                size: 44,
                color: theme.colorScheme.onSurfaceVariant,
              ),
              const SizedBox(height: 14),
              Text('No stories yet', style: theme.textTheme.titleMedium),
              const SizedBox(height: 8),
              Text(
                'A tree records that somebody existed. A story is where the '
                'family keeps why that mattered.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: onWrite,
                icon: const Icon(Icons.edit_outlined),
                label: const Text('Write one'),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
      itemCount: stories.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) =>
          _StoryCard(story: stories[index], onTap: () => onOpen(stories[index])),
    );
  }
}

class _StoryCard extends StatelessWidget {
  const _StoryCard({required this.story, required this.onTap});

  final Story story;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final audience = story.audienceLabel;
    final era = story.eraLabel;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(story.title, style: theme.textTheme.titleMedium),
              if (story.summary != null) ...[
                const SizedBox(height: 6),
                Text(
                  story.summary!,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 6,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  if (era != null) _Chip(label: era, icon: Icons.schedule),
                  // Who may read it, shown because somebody writing about
                  // living relatives should be able to see at a glance that
                  // they did not accidentally publish it.
                  if (audience != null) _Chip(label: audience, icon: Icons.lock_outline),
                  if (story.authorName != null)
                    Text(
                      'by ${story.authorName}',
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.icon});

  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 6),
          Text(label, style: theme.textTheme.labelMedium),
        ],
      ),
    );
  }
}
