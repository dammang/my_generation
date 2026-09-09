import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/errors/api_exception.dart';
import '../../models/joinable_scope.dart';
import '../../providers/onboarding_provider.dart';
import '../../widgets/form_banner.dart';

/// Asking to join something — a tribe, or a clan within one.
///
/// One screen for both, because they are the same request to the same endpoint
/// reviewed by the same people. Asking is all it does: approval belongs to
/// whoever administers the scope, and the screen says so plainly rather than
/// implying the request was enough.
class JoinScopeScreen extends ConsumerStatefulWidget {
  const JoinScopeScreen({
    super.key,
    required this.title,
    required this.intro,
    required this.searchLabel,
    required this.emptyHint,
    required this.watch,
    required this.refresh,
    this.actions,
  });

  final String title;
  final String intro;
  final String searchLabel;

  /// What to tell somebody who finds nothing — the answer is never "try
  /// again", it is "ask the person who set up your family's archive".
  final String emptyHint;

  /// Whatever can be joined, for a given search. Passed as callbacks rather
  /// than a provider so the screen never has to know which provider family it
  /// is reading.
  final AsyncValue<List<JoinableScope>> Function(WidgetRef, String) watch;
  final void Function(WidgetRef, String) refresh;

  final List<Widget>? actions;

  @override
  ConsumerState<JoinScopeScreen> createState() => _JoinScopeScreenState();
}

class _JoinScopeScreenState extends ConsumerState<JoinScopeScreen> {
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

  Future<void> _request(JoinableScope scope) async {
    setState(() {
      _requestingUlid = scope.ulid;
      _error = null;
    });

    try {
      await ref
          .read(onboardingRepositoryProvider)
          .requestMembership(scopeType: scope.type, scopeUlid: scope.ulid);

      ref.invalidate(myMembershipsProvider);
      ref.invalidate(needsOnboardingProvider);

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Asked to join ${scope.name}. An administrator will review it.',
            ),
          ),
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
    final scopes = widget.watch(ref, _query);
    final memberships = ref.watch(myMembershipsProvider);

    final requested = memberships.maybeWhen(
      data: (list) => list.map((m) => m.scopeUlid).whereType<String>().toSet(),
      orElse: () => <String>{},
    );

    return Scaffold(
      appBar: AppBar(title: Text(widget.title), actions: widget.actions),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  widget.intro,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _search,
                  onChanged: _onSearchChanged,
                  textInputAction: TextInputAction.search,
                  decoration: InputDecoration(
                    labelText: widget.searchLabel,
                    prefixIcon: const Icon(Icons.search),
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
            child: scopes.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => _Retry(
                message: error is ApiException
                    ? error.message
                    : 'Could not load the list.',
                onRetry: () => widget.refresh(ref, _query),
              ),
              data: (list) => list.isEmpty
                  ? _Empty(query: _query, hint: widget.emptyHint)
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(20, 4, 20, 32),
                      itemCount: list.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 10),
                      itemBuilder: (context, index) {
                        final scope = list[index];
                        final asked = requested.contains(scope.ulid);

                        return _ScopeCard(
                          scope: scope,
                          alreadyRequested: asked,
                          busy: _requestingUlid == scope.ulid,
                          onRequest: asked ? null : () => _request(scope),
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

class _ScopeCard extends StatelessWidget {
  const _ScopeCard({
    required this.scope,
    required this.alreadyRequested,
    required this.busy,
    required this.onRequest,
  });

  final JoinableScope scope;
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
                      Text(scope.name, style: theme.textTheme.titleLarge),
                      if (scope.nativeName != null &&
                          scope.nativeName != scope.name)
                        Text(
                          scope.nativeName!,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      const SizedBox(height: 4),
                      Text(scope.subtitle, style: theme.textTheme.labelMedium),
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
            if (scope.description != null && scope.description!.isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                scope.description!,
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
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2.2),
                      )
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
  const _Empty({required this.query, required this.hint});

  final String query;
  final String hint;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.groups_outlined,
              size: 44,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 16),
            Text(
              query.isEmpty ? hint : 'Nothing matches “$query”.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge,
            ),
            const SizedBox(height: 8),
            Text(
              'Ask whoever set up your family archive which one to join.',
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
