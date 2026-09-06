import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/constants/api_paths.dart';
import '../../../models/api_user.dart';
import '../../../providers/auth_provider.dart';
import '../../../providers/onboarding_provider.dart';
import '../../../routing/app_router.dart';

/// The account, as distinct from the person.
///
/// This is where everything that describes the signed-in account now lives —
/// who they are, what they can reach, which server they are talking to, and how
/// to leave. It was all on home before, which made home a settings page wearing
/// a dashboard's name and left no room for the archive itself.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final auth = ref.watch(authProvider);

    if (auth is! AuthSignedIn) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final user = auth.user;

    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: RefreshIndicator(
        onRefresh: () => ref.read(authProvider.notifier).refresh(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 28,
                          backgroundColor: theme.colorScheme.primaryContainer,
                          child: Text(
                            _initials(user.name),
                            style: theme.textTheme.titleLarge?.copyWith(
                              color: theme.colorScheme.onPrimaryContainer,
                            ),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                user.name,
                                style: theme.textTheme.titleLarge,
                              ),
                              Text(
                                user.email,
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  color: theme.colorScheme.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 32),
                    // A user is not a person: most accounts are never linked to
                    // a genealogy record, and saying so plainly is better than
                    // an empty space.
                    _Fact(
                      label: 'Archive profile',
                      // Terse because the button directly below says what to do
                      // about it, and because anything longer wraps onto a
                      // second right-aligned line, which reads badly.
                      value: user.hasClaimedPerson
                          ? (user.personName ?? 'Linked')
                          : 'Not linked yet',
                      icon: user.hasClaimedPerson ? Icons.link : Icons.link_off,
                    ),
                    if (user.hasClaimedPerson) ...[
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: () =>
                            context.push(Routes.personPath(user.personUlid!)),
                        icon: const Icon(Icons.badge_outlined),
                        label: const Text('Open my record'),
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(48),
                        ),
                      ),
                    ] else ...[
                      // A record usually exists before its subject opens the
                      // app, so this is an ordinary next step rather than an
                      // error to be corrected.
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: () => context.push(Routes.claimProfile),
                        icon: const Icon(Icons.person_search_outlined),
                        label: const Text('Find myself in the archive'),
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(48),
                        ),
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
                    Text(
                      'What this account can reach',
                      style: theme.textTheme.titleMedium,
                    ),
                    const SizedBox(height: 12),
                    _Fact(
                      label: 'Tribes',
                      value: user.tribeIds.isEmpty
                          ? 'No memberships yet'
                          : '${user.tribeIds.length}',
                      icon: Icons.groups_outlined,
                      onTap: user.tribeIds.isEmpty
                          ? null
                          : () => _showMemberships(context, ref, 'tribe'),
                    ),
                    _Fact(
                      label: 'Clans',
                      value: '${user.clanIds.length}',
                      icon: Icons.account_tree_outlined,
                      onTap: user.clanIds.isEmpty
                          ? null
                          : () => _showMemberships(context, ref, 'clan'),
                    ),
                    _Fact(
                      label: 'Permissions',
                      value: user.isSuperAdmin
                          ? 'Full administrator'
                          : '${user.permissions.length}',
                      icon: Icons.key_outlined,
                      // "Why can't I approve this?" is answered by the list and
                      // not by the number, and it is the question an admin
                      // actually arrives with.
                      onTap: () => _showPermissions(context, user),
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
                    Text(
                      ApiConfig.defaultBaseUrl,
                      style: theme.textTheme.bodyLarge,
                    ),
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
            const SizedBox(height: 24),
            OutlinedButton.icon(
              onPressed: () => _confirmSignOut(context, ref),
              icon: const Icon(Icons.logout),
              label: const Text('Sign out'),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
                foregroundColor: theme.colorScheme.error,
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Which tribes or clans, rather than how many.
  ///
  /// Read from the memberships endpoint because /auth/me carries scope ids and
  /// no names — the count came from a list the screen could not show.
  void _showMemberships(BuildContext context, WidgetRef ref, String type) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => Consumer(
        builder: (context, ref, _) {
          final memberships = ref.watch(myMembershipsProvider);
          final theme = Theme.of(context);
          final heading = type == 'tribe' ? 'Tribes' : 'Clans';

          return SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
              child: memberships.when(
                loading: () => const Padding(
                  padding: EdgeInsets.all(32),
                  child: Center(child: CircularProgressIndicator()),
                ),
                error: (_, _) => Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    'Could not read your memberships.',
                    style: theme.textTheme.bodyLarge,
                  ),
                ),
                data: (all) {
                  final rows = all.where((m) => m.scopeType == type).toList();

                  return Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(heading, style: theme.textTheme.titleLarge),
                      const SizedBox(height: 12),
                      if (rows.isEmpty)
                        Text(
                          'Nothing here yet.',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        )
                      else
                        for (final m in rows)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: Icon(
                              type == 'tribe'
                                  ? Icons.groups_outlined
                                  : Icons.account_tree_outlined,
                            ),
                            title: Text(m.scopeName ?? 'Unnamed'),
                            // Pending grants nothing, and a list that showed
                            // both the same way would be quietly wrong.
                            subtitle: Text(
                              m.isActive ? 'Member' : 'Waiting for approval',
                            ),
                            trailing: m.isActive
                                ? const Icon(
                                    Icons.check_circle_outline,
                                    size: 20,
                                  )
                                : Icon(
                                    Icons.schedule,
                                    size: 20,
                                    color: theme.colorScheme.tertiary,
                                  ),
                          ),
                    ],
                  );
                },
              ),
            ),
          );
        },
      ),
    );
  }

  /// Exactly what this account may do, named.
  void _showPermissions(BuildContext context, ApiUser user) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        final theme = Theme.of(context);

        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Permissions', style: theme.textTheme.titleLarge),
                const SizedBox(height: 4),
                Text(
                  user.isSuperAdmin
                      ? 'A full administrator bypasses every check below.'
                      : 'Some of these apply only inside a tribe or clan you '
                            'belong to.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 12),
                if (user.permissions.isEmpty)
                  Text('None granted.', style: theme.textTheme.bodyLarge)
                else
                  Flexible(
                    child: SingleChildScrollView(
                      child: Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final p in ([...user.permissions]..sort()))
                            Chip(label: Text(p)),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  /// Asked for rather than assumed.
  ///
  /// Signing out drops the token, and anything still in the outbox goes with
  /// the session — a stray tap on a tab bar should not be able to do that
  /// silently.
  Future<void> _confirmSignOut(BuildContext context, WidgetRef ref) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Sign out?'),
        content: const Text(
          'You will need to sign in again to read anything that is not already '
          'saved on this device.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Stay signed in'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Sign out'),
          ),
        ],
      ),
    );

    if (confirmed ?? false) {
      await ref.read(authProvider.notifier).signOut();
    }
  }

  /// Falls back to a single letter rather than showing an empty circle, and to
  /// nothing at all rather than throwing on a name we were never sent.
  static String _initials(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((p) => p.isNotEmpty)
        .toList();

    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.characters.first.toUpperCase();

    return (parts.first.characters.first + parts.last.characters.first)
        .toUpperCase();
  }
}

/// A labelled value, on one line where it fits and two where it does not.
///
/// The value used to be an unconstrained Text beside an Expanded label, which
/// overflowed by 156 pixels the moment the value was a sentence rather than a
/// number — and "Not yet linked to a person" always is. Giving both halves a
/// flex lets the long ones wrap instead of running off the card.
class _Fact extends StatelessWidget {
  const _Fact({
    required this.label,
    required this.value,
    required this.icon,
    this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;

  /// A count answers "how many" and hides "which". Where the answer is worth
  /// having, the row opens it rather than leaving somebody to guess.
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    final row = Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 12),
          Expanded(child: Text(label, style: theme.textTheme.bodyLarge)),
          const SizedBox(width: 12),
          // Right-aligned rather than right-placed: the box reaches the edge of
          // the card either way, so a one-word value still lands where the eye
          // expects it and a long one has somewhere to wrap to.
          Expanded(
            child: Text(
              value,
              style: theme.textTheme.titleMedium,
              textAlign: TextAlign.end,
            ),
          ),
          if (onTap != null)
            Icon(
              Icons.chevron_right,
              size: 18,
              color: theme.colorScheme.onSurfaceVariant,
            ),
        ],
      ),
    );

    if (onTap == null) return row;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: row,
    );
  }
}
