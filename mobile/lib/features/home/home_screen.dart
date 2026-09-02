import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:go_router/go_router.dart';

import '../../core/constants/api_paths.dart';
import '../../providers/auth_provider.dart';
import '../../providers/onboarding_provider.dart';
import '../../routing/app_router.dart';

/// A placeholder home.
///
/// Its job in this phase is to prove the whole chain: a token from the
/// keystore, a request through Dio, an envelope unwrapped, and the viewer's
/// real scopes and permissions on screen. The actual home — lineage strip,
/// statistics, recent family history — arrives with the feature phases.
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
      appBar: AppBar(
        title: const Text('My Generation'),
        actions: [
          IconButton(
            tooltip: 'Sign out',
            onPressed: () => ref.read(authProvider.notifier).signOut(),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await ref.read(authProvider.notifier).refresh();
          ref.invalidate(myMembershipsProvider);
          ref.invalidate(myClaimsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Signed in as', style: theme.textTheme.labelMedium),
                    const SizedBox(height: 4),
                    Text(user.name, style: theme.textTheme.headlineSmall),
                    Text(
                      user.email,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 16),
                    // A user is not a person: most accounts are never linked to
                    // a genealogy record, and saying so plainly is better than
                    // an empty space.
                    _Fact(
                      label: 'Archive profile',
                      value: user.hasClaimedPerson
                          ? 'Linked to ${user.personName}'
                          : 'Not yet linked to a person',
                      icon: user.hasClaimedPerson ? Icons.link : Icons.link_off,
                    ),
                    // A record usually exists before its subject opens the app,
                    // so this is an ordinary next step rather than an error to
                    // be corrected.
                    if (!user.hasClaimedPerson) ...[
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: () => context.push(Routes.claimProfile),
                        icon: const Icon(Icons.person_search_outlined),
                        label: const Text('Find myself in the archive'),
                        style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('What this account can reach', style: theme.textTheme.titleMedium),
                    const SizedBox(height: 12),
                    _Fact(
                      label: 'Tribes',
                      value: user.tribeIds.isEmpty ? 'No memberships yet' : '${user.tribeIds.length}',
                      icon: Icons.groups_outlined,
                    ),
                    _Fact(
                      label: 'Clans',
                      value: '${user.clanIds.length}',
                      icon: Icons.account_tree_outlined,
                    ),
                    _Fact(
                      label: 'Permissions',
                      value: user.isSuperAdmin
                          ? 'Full administrator'
                          : '${user.permissions.length}',
                      icon: Icons.key_outlined,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            _PendingRequests(),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Connected to', style: theme.textTheme.labelMedium),
                    const SizedBox(height: 4),
                    Text(ApiConfig.defaultBaseUrl, style: theme.textTheme.bodyLarge),
                    const SizedBox(height: 4),
                    Text(
                      'Pull down to re-read your account from the server.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
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
        data: (list) => list.where((m) => m.isPending).map(
              (m) => 'Joining ${m.scopeName ?? 'a tribe'}',
            ),
        orElse: () => const <String>[],
      ),
      ...claims.maybeWhen(
        data: (list) => list.where((c) => c.isPending).map(
              (c) => 'Being recognised as ${c.personName ?? 'a person'}',
            ),
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
                      Icon(Icons.schedule, size: 18, color: theme.colorScheme.tertiary),
                      const SizedBox(width: 10),
                      Expanded(child: Text(item, style: theme.textTheme.bodyLarge)),
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

class _Fact extends StatelessWidget {
  const _Fact({required this.label, required this.value, required this.icon});

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Icon(icon, size: 20, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 12),
          Expanded(child: Text(label, style: theme.textTheme.bodyLarge)),
          Text(value, style: theme.textTheme.titleMedium),
        ],
      ),
    );
  }
}
