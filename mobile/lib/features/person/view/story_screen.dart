import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../models/story.dart';
import '../../../providers/story_provider.dart';

/// One story, read.
///
/// The body arrives with this screen rather than with the list, so opening a
/// story is the moment its full text is fetched.
class StoryScreen extends ConsumerWidget {
  const StoryScreen({super.key, required this.ulid});

  final String ulid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final story = ref.watch(storyProvider(ulid));

    return Scaffold(
      appBar: AppBar(title: const Text('Story')),
      body: story.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) =>
            _Failure(message: '$error', onRetry: () => ref.invalidate(storyProvider(ulid))),
        data: (story) => _Body(story: story),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({required this.story});

  final Story story;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final audience = story.audienceLabel;
    final era = story.eraLabel;

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 48),
      children: [
        Text(story.title, style: theme.textTheme.headlineSmall),
        const SizedBox(height: 10),
        Text(
          [
            if (story.authorName != null) 'by ${story.authorName}',
            if (story.subjectName != null) 'about ${story.subjectName}',
            ?era,
            ?audience,
          ].join(' · '),
          style: theme.textTheme.labelMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
        ),
        const Divider(height: 32),
        if (story.hasBody)
          // Long-form prose: taller line spacing, because a paragraph set at
          // list density is unpleasant to read for more than a sentence.
          Text(story.body!, style: theme.textTheme.bodyLarge?.copyWith(height: 1.6))
        else
          Text(
            'This story has no text yet.',
            style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
          ),
      ],
    );
  }
}

class _Failure extends StatelessWidget {
  const _Failure({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.error_outline,
              size: 44,
              color: Theme.of(context).colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 14),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 20),
            OutlinedButton(onPressed: onRetry, child: const Text('Try again')),
          ],
        ),
      ),
    );
  }
}
