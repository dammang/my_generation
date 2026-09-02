import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/constants/api_paths.dart';
import '../../providers/auth_provider.dart';

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
        onRefresh: () => ref.read(authProvider.notifier).refresh(),
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
