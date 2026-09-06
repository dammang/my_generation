import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/api_exception.dart';
import '../../../providers/clan_provider.dart';
import '../../../routing/app_router.dart';

/// The tribes and clans this account runs.
///
/// Almost every account runs nothing, so this screen is reached from a tile
/// that only appears once there is something in it.
class AdministeredScopesScreen extends ConsumerWidget {
  const AdministeredScopesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final scopes = ref.watch(administeredScopesProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Families you run')),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(administeredScopesProvider),
        child: scopes.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => ListView(
            padding: const EdgeInsets.all(32),
            children: [
              Text(
                error is ApiException
                    ? error.message
                    : 'Could not read what you administer.',
                textAlign: TextAlign.center,
              ),
            ],
          ),
          data: (all) {
            if (all.isEmpty) {
              return ListView(
                padding: const EdgeInsets.fromLTRB(32, 80, 32, 32),
                children: [
                  Icon(
                    Icons.shield_outlined,
                    size: 44,
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'You do not run anything yet',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Whoever runs a clan records where its tree begins and '
                    'appoints its committee. Start a clan, or ask an '
                    'administrator to appoint you.',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              );
            }

            return ListView.separated(
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: all.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, i) {
                final scope = all[i];

                return ListTile(
                  leading: Icon(
                    scope.scopeType == 'tribe'
                        ? Icons.groups_outlined
                        : Icons.account_tree_outlined,
                  ),
                  title: Text(scope.name),
                  subtitle: Text(scope.scopeType == 'tribe' ? 'Tribe' : 'Clan'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push(
                    Routes.committeePath(scope.scopeType, scope.scopeUlid),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
