import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:go_router/go_router.dart';

import '../../providers/auth_provider.dart';
import '../../providers/onboarding_provider.dart';
import '../../routing/app_router.dart';
import '../auth/widgets/verify_email_banner.dart';
import '../sync/widgets/sync_banner.dart';

/// Where the app opens.
///
/// Everything describing the account itself moved to the profile tab when the
/// bottom bar arrived; what is left is about the archive — what needs somebody's
/// attention, and the two places worth going next.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final auth = ref.watch(authProvider);

    if (auth is! AuthSignedIn) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final user = auth.user;

    return Scaffold(
      appBar: AppBar(title: const Text('My Generation')),
      body: Column(
        children: [
          // Above everything else: what has not reached the server yet changes
          // how the rest of the screen should be read. Tapping switches to the
          // outbox tab rather than stacking a copy of it on top of home.
          SyncBanner(onTap: () => context.go(Routes.pendingChanges)),

          // Above that again when it shows at all, because an unconfirmed
          // address means nothing below can be contributed to yet.
          const VerifyEmailBanner(),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async {
                await ref.read(authProvider.notifier).refresh();
                ref.invalidate(myMembershipsProvider);
                ref.invalidate(myClaimsProvider);
              },
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Text(
                    _greeting(user.name),
                    style: theme.textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    user.hasClaimedPerson
                        ? 'You are recorded here as ${user.personName}.'
                        : 'Your account is not linked to anyone in the archive yet.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),

                  // A record usually exists before its subject opens the app, so
                  // this is an ordinary next step rather than an error to be
                  // corrected. It stays on home because it is the one thing a
                  // new arrival most needs to do.
                  if (!user.hasClaimedPerson) ...[
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: () => context.push(Routes.claimProfile),
                      icon: const Icon(Icons.person_search_outlined),
                      label: const Text('Find myself in the archive'),
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(48),
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  _PendingRequests(),
                  _Shortcut(
                    icon: Icons.account_tree_outlined,
                    title: 'Family tree',
                    subtitle: user.hasClaimedPerson
                        ? 'Start from ${user.personName}'
                        : 'Browse the people you can see',
                    onTap: () => context.go(Routes.tree),
                  ),
                  const SizedBox(height: 16),
                  _Shortcut(
                    icon: Icons.rate_review_outlined,
                    title: 'Contributions',
                    subtitle:
                        'Corrections you have suggested, and any waiting '
                        'for you to review',
                    onTap: () => context.go(Routes.contributions),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  /// First name only. "Welcome back, Nguyen Van Minh" reads as a form letter;
  /// the short form reads as somebody's own archive.
  static String _greeting(String name) {
    final first = name.trim().split(RegExp(r'\s+')).first;

    return first.isEmpty ? 'Welcome back' : 'Welcome back, $first';
  }
}

/// A place worth going, stated as a place rather than a menu row.
class _Shortcut extends StatelessWidget {
  const _Shortcut({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Row(
            children: [
              Icon(icon, size: 32, color: theme.colorScheme.primary),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: theme.textTheme.titleLarge),
                    Text(
                      subtitle,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

/// Requests that are waiting on somebody else.
///
/// Pending grants nothing, and silence about it reads as the request having
/// been lost. Showing the wait is kinder than showing nothing.
class _PendingRequests extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final memberships = ref.watch(myMembershipsProvider);
    final claims = ref.watch(myClaimsProvider);

    final pending = <String>[
      ...memberships.maybeWhen(
        data: (list) => list
            .where((m) => m.isPending)
            .map((m) => 'Joining ${m.scopeName ?? 'a tribe'}'),
        orElse: () => const <String>[],
      ),
      ...claims.maybeWhen(
        data: (list) => list
            .where((c) => c.isPending)
            .map((c) => 'Being recognised as ${c.personName ?? 'a person'}'),
        orElse: () => const <String>[],
      ),
    ];

    if (pending.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Waiting for approval', style: theme.textTheme.titleMedium),
              const SizedBox(height: 4),
              Text(
                'Someone in your family needs to confirm these.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 12),
              for (final item in pending)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    children: [
                      Icon(
                        Icons.schedule,
                        size: 18,
                        color: theme.colorScheme.tertiary,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(item, style: theme.textTheme.bodyLarge),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
