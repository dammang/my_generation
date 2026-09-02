import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/change_request.dart';
import '../../../providers/person_provider.dart';
import '../../../providers/review_provider.dart';
import '../widgets/diff_table.dart';

/// The review queue, from whichever side the viewer is on.
///
/// Two tabs rather than two screens: they are the same records, and a
/// contributor who is also a reviewer should not have to remember which screen
/// their own proposal lives on.
class ReviewQueueScreen extends ConsumerWidget {
  const ReviewQueueScreen({super.key, this.initialTab = 0});

  /// Which side to open on. A "3 changes waiting" notification should land on
  /// the queue itself, not on a tab the reviewer has to find.
  final int initialTab;

  static int tabIndexFor(String? name) => name == 'review' ? 1 : 0;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final canReview = ref.watch(canReviewProvider);

    // The tab bar is only built once the server has said whether this account
    // reviews anything. Building it first and changing its length afterwards
    // rebuilds the controller, which drops the tab the caller asked for and
    // makes a deep link land on the wrong side of the screen.
    return canReview.when(
      loading: () =>
          const _Shell(body: Center(child: CircularProgressIndicator())),
      error: (_, _) => const _Shell(body: _QueueList(filter: 'mine')),
      data: (canReview) => canReview
          ? _BothSides(initialTab: initialTab)
          : const _Shell(body: _QueueList(filter: 'mine')),
    );
  }
}

/// The screen without tabs, for somebody who only ever sees their own.
class _Shell extends StatelessWidget {
  const _Shell({required this.body});

  final Widget body;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Contributions')),
    body: body,
  );
}

class _BothSides extends ConsumerWidget {
  const _BothSides({required this.initialTab});

  final int initialTab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return DefaultTabController(
      length: 2,
      initialIndex: initialTab,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Contributions'),
          bottom: TabBar(
            onTap: (index) => ref
                .read(queueFilterProvider.notifier)
                .show(index == 0 ? 'mine' : 'review'),
            tabs: const [
              Tab(text: 'Mine'),
              Tab(text: 'To review'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [
            _QueueList(filter: 'mine'),
            _QueueList(filter: 'review'),
          ],
        ),
      ),
    );
  }
}

class _QueueList extends ConsumerWidget {
  const _QueueList({required this.filter});

  final String filter;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final queue = ref.watch(reviewQueueProvider(filter));

    return queue.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Text(
            error is ApiException ? error.message : 'Something went wrong.',
            textAlign: TextAlign.center,
          ),
        ),
      ),
      data: (data) {
        if (data.requests.isEmpty) {
          return _Empty(filter: filter);
        }

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(reviewQueueProvider(filter)),
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 32),
            itemCount: data.requests.length,
            itemBuilder: (context, index) => _RequestCard(
              request: data.requests[index],
              reviewing: filter == 'review',
            ),
          ),
        );
      },
    );
  }
}

class _RequestCard extends ConsumerStatefulWidget {
  const _RequestCard({required this.request, required this.reviewing});

  final ChangeRequestSummary request;
  final bool reviewing;

  @override
  ConsumerState<_RequestCard> createState() => _RequestCardState();
}

class _RequestCardState extends ConsumerState<_RequestCard> {
  bool _busy = false;

  ChangeRequestSummary get request => widget.request;

  Future<void> _decide(Future<void> Function() action) async {
    setState(() => _busy = true);

    try {
      await action();

      ref.invalidate(reviewQueueProvider('review'));
      ref.invalidate(reviewQueueProvider('mine'));

      if (request.targetUlid != null) {
        invalidatePerson(ref, request.targetUlid!);
      }
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);

      // A record that moved underneath the proposal is not a failure to report
      // and forget — the reviewer needs to see what changed and decide again.
      if (error.code == 'CHANGE_REQUEST_SUPERSEDED') {
        await _showConflict(error);
        ref.invalidate(reviewQueueProvider('review'));
        return;
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  Future<void> _showConflict(ApiException error) async {
    final conflicts = ((error.meta['conflicts'] as List?) ?? const [])
        .whereType<Map>()
        .toList(growable: false);

    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('The record changed first'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Somebody else edited this while the suggestion was waiting. '
              'Nothing was overwritten.',
            ),
            const SizedBox(height: 14),
            for (final conflict in conflicts)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      (conflict['field'] ?? '').toString(),
                      style: Theme.of(context).textTheme.labelMedium,
                    ),
                    Text(
                      'was ${conflict['was'] ?? '—'} · now ${conflict['now'] ?? '—'}',
                    ),
                  ],
                ),
              ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Got it'),
          ),
        ],
      ),
    );
  }

  Future<String?> _askForComment(String title) async {
    final controller = TextEditingController();

    final comment = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: 'Why? The contributor will see this.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(controller.text.trim()),
            child: const Text('Send'),
          ),
        ],
      ),
    );

    controller.dispose();

    return comment;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final repository = ref.read(reviewRepositoryProvider);

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
                  child: Text(
                    request.targetLabel ?? 'A record',
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                _StatusChip(request: request),
              ],
            ),
            if (widget.reviewing && request.requestedByName != null)
              Text(
                'Suggested by ${request.requestedByName}',
                style: theme.textTheme.labelMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            const SizedBox(height: 14),
            DiffTable(diff: request.diff),
            if (request.reason != null && request.reason!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: theme.colorScheme.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(request.reason!, style: theme.textTheme.bodyMedium),
              ),
            ],
            for (final comment in request.reviewComments)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.reply,
                      size: 16,
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(comment, style: theme.textTheme.bodyMedium),
                    ),
                  ],
                ),
              ),
            if (_busy)
              const Padding(
                padding: EdgeInsets.only(top: 14),
                child: LinearProgressIndicator(),
              )
            else if (widget.reviewing && request.isPending)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                // Wrap rather than Row: the buttons keep their own intrinsic
                // width instead of depending on a bounded parent, and fall onto
                // a second line at large text sizes rather than overflowing.
                // Aligned by the parent, because a Wrap shrinks to its children
                // and so cannot right-align them itself.
                child: Align(
                  alignment: Alignment.centerRight,
                  child: Wrap(
                    alignment: WrapAlignment.end,
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      TextButton(
                        onPressed: () async {
                          final comment = await _askForComment(
                            'Not accepting this',
                          );
                          if (comment == null) return;
                          await _decide(
                            () => repository.reject(
                              request.ulid,
                              comment: comment,
                            ),
                          );
                        },
                        child: const Text('Decline'),
                      ),
                      FilledButton(
                        onPressed: () =>
                            _decide(() => repository.approve(request.ulid)),
                        child: const Text('Accept'),
                      ),
                    ],
                  ),
                ),
              )
            else if (!widget.reviewing && request.isPending)
              Align(
                alignment: Alignment.centerRight,
                child: TextButton(
                  onPressed: () =>
                      _decide(() => repository.withdraw(request.ulid)),
                  child: const Text('Withdraw'),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.request});

  final ChangeRequestSummary request;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    final colour = switch (request.status) {
      'approved' => theme.colorScheme.primary,
      'rejected' || 'superseded' => theme.colorScheme.error,
      _ => theme.colorScheme.onSurfaceVariant,
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: colour.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        request.statusLabel,
        style: theme.textTheme.labelSmall?.copyWith(color: colour),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.filter});

  final String filter;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final reviewing = filter == 'review';

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(36),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              reviewing ? Icons.inbox_outlined : Icons.edit_note_outlined,
              size: 44,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 14),
            Text(
              reviewing
                  ? 'Nothing waiting'
                  : 'You have not suggested anything yet',
              style: theme.textTheme.titleMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              reviewing
                  ? 'Suggestions from your clan will appear here.'
                  : 'When you correct a record that has been checked, the '
                        'change is suggested rather than applied, and appears here.',
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
}
