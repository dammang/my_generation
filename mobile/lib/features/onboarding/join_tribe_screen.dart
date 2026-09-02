import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/errors/api_exception.dart';
import '../../models/tribe_summary.dart';
import '../../providers/auth_provider.dart';
import '../../providers/onboarding_provider.dart';
import '../../widgets/form_banner.dart';

/// The first question a new account is asked.
///
/// Somebody with no membership can see almost nothing, so landing them on an
/// empty home would be a worse first impression than asking once. Asking is all
/// it does: approval belongs to whoever administers the tribe, and the screen
/// says so plainly rather than implying the request was enough.
class JoinTribeScreen extends ConsumerStatefulWidget {
  const JoinTribeScreen({super.key});

  @override
  ConsumerState<JoinTribeScreen> createState() => _JoinTribeScreenState();
}

class _JoinTribeScreenState extends ConsumerState<JoinTribeScreen> {
  final _search = TextEditingController();
  Timer? _debounce;
  String _query = '';
  String? _requestingUlid;
  String? _error;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    // A request per keystroke would burn the search throttle in seconds.
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) setState(() => _query = value.trim());
    });
  }

  Future<void> _request(TribeSummary tribe) async {
    setState(() {
      _requestingUlid = tribe.ulid;
      _error = null;
    });

    try {
      await ref.read(onboardingRepositoryProvider).requestMembership(
            scopeType: 'tribe',
            scopeUlid: tribe.ulid,
          );

      ref.invalidate(myMembershipsProvider);
      ref.invalidate(needsOnboardingProvider);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Asked to join ${tribe.name}. An administrator will review it.')),
        );
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _requestingUlid = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tribes = ref.watch(tribesProvider(_query));
    final memberships = ref.watch(myMembershipsProvider);

    final requested = memberships.maybeWhen(
      data: (list) => list.map((m) => m.scopeUlid).whereType<String>().toSet(),
      orElse: () => <String>{},
    );

    return Scaffold(
      appBar: AppBar(
        title: const Text('Find your family'),
        actions: [
          TextButton(
            onPressed: () => ref.read(authProvider.notifier).signOut(),
            child: const Text('Sign out'),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Join the tribe your family belongs to. Until an administrator '
                  'approves you, you will only see what is public.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _search,
                  onChanged: _onSearchChanged,
                  textInputAction: TextInputAction.search,
                  decoration: const InputDecoration(
                    labelText: 'Search tribes',
                    prefixIcon: Icon(Icons.search),
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 14),
                  FormBanner(message: _error!, tone: theme.colorScheme.error),
                ],
                const SizedBox(height: 8),
              ],
            ),
          ),
          Expanded(
            child: tribes.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => _Retry(
                message: error is ApiException ? error.message : 'Could not load tribes.',
                onRetry: () => ref.invalidate(tribesProvider(_query)),
              ),
              data: (list) => list.isEmpty
                  ? _Empty(query: _query)
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(20, 4, 20, 32),
                      itemCount: list.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 10),
                      itemBuilder: (context, index) {
                        final tribe = list[index];
                        final asked = requested.contains(tribe.ulid);

                        return _TribeCard(
                          tribe: tribe,
                          alreadyRequested: asked,
                          busy: _requestingUlid == tribe.ulid,
                          onRequest: asked ? null : () => _request(tribe),
                        );
                      },
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TribeCard extends StatelessWidget {
  const _TribeCard({
    required this.tribe,
    required this.alreadyRequested,
    required this.busy,
    required this.onRequest,
  });

  final TribeSummary tribe;
  final bool alreadyRequested;
  final bool busy;
  final VoidCallback? onRequest;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(tribe.name, style: theme.textTheme.titleLarge),
                      if (tribe.nativeName != null && tribe.nativeName != tribe.name)
                        Text(
                          tribe.nativeName!,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      const SizedBox(height: 4),
                      Text(tribe.subtitle, style: theme.textTheme.labelMedium),
                    ],
                  ),
                ),
                if (alreadyRequested)
                  Chip(
                    avatar: const Icon(Icons.schedule, size: 18),
                    label: const Text('Requested'),
                    visualDensity: VisualDensity.compact,
                  ),
              ],
            ),
            if (tribe.description != null && tribe.description!.isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                tribe.description!,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodyMedium,
              ),
            ],
            if (!alreadyRequested) ...[
              const SizedBox(height: 14),
              FilledButton.tonal(
                onPressed: busy ? null : onRequest,
                child: busy
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.2))
                    : const Text('Ask to join'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.query});

  final String query;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.groups_outlined, size: 44, color: theme.colorScheme.onSurfaceVariant),
            const SizedBox(height: 16),
            Text(
              query.isEmpty
                  ? 'No tribes have been created yet.'
                  : 'No tribe matches “$query”.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge,
            ),
            const SizedBox(height: 8),
            Text(
              'Ask whoever set up your family archive which tribe to join.',
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

class _Retry extends StatelessWidget {
  const _Retry({required this.message, required this.onRetry});

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
            const Icon(Icons.cloud_off_outlined, size: 40),
            const SizedBox(height: 14),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 20),
            FilledButton(onPressed: onRetry, child: const Text('Try again')),
          ],
        ),
      ),
    );
  }
}
