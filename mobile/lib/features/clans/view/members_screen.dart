import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/constants/countries.dart';
import '../../../core/errors/api_exception.dart';
import '../../../models/committee.dart';
import '../../../models/membership.dart';
import '../../../providers/clan_provider.dart';
import '../../../providers/onboarding_provider.dart';

/// Everybody in a family you run, and what they told us about themselves.
///
/// A roll rather than a queue: the join requests page empties as it is worked
/// through, this one stays. Filtered by clan because a clan is what a
/// committee actually administers, and there will be more than one.
class MembersScreen extends ConsumerStatefulWidget {
  const MembersScreen({super.key});

  @override
  ConsumerState<MembersScreen> createState() => _MembersScreenState();
}

class _MembersScreenState extends ConsumerState<MembersScreen> {
  AdministeredScope? _chosen;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scopes = ref.watch(administeredScopesProvider).value ?? const [];
    final chosen = _chosen ?? (scopes.isEmpty ? null : scopes.first);

    return Scaffold(
      appBar: AppBar(title: const Text('Members')),
      body: scopes.isEmpty
          ? _Empty(
              theme: theme,
              message: 'You do not run a family yet.',
              detail: 'Members appear here for the clans you administer.',
            )
          : Column(
              children: [
                // Only worth showing once there is a choice to make.
                if (scopes.length > 1)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 4),
                    child: SizedBox(
                      width: double.infinity,
                      child: SegmentedButton<String>(
                        segments: [
                          for (final scope in scopes)
                            ButtonSegment(
                              value: scope.scopeUlid,
                              label: Text(scope.name),
                            ),
                        ],
                        selected: {chosen!.scopeUlid},
                        onSelectionChanged: (chosen) => setState(
                          () => _chosen = scopes.firstWhere(
                            (s) => s.scopeUlid == chosen.first,
                          ),
                        ),
                      ),
                    ),
                  ),
                Expanded(child: _Members(scope: chosen!)),
              ],
            ),
    );
  }
}

class _Members extends ConsumerWidget {
  const _Members({required this.scope});

  final AdministeredScope scope;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final key = (type: scope.scopeType, ulid: scope.scopeUlid);
    final members = ref.watch(scopeMembersProvider(key));

    return members.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => _Empty(
        theme: theme,
        message: error is ApiException
            ? error.message
            : 'Could not read the members.',
        detail: 'Pull down to try again.',
      ),
      data: (rows) => rows.isEmpty
          ? _Empty(
              theme: theme,
              message: 'Nobody has joined ${scope.name} yet.',
              detail: 'Approved requests appear here.',
            )
          : RefreshIndicator(
              onRefresh: () async => ref.invalidate(scopeMembersProvider(key)),
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                itemCount: rows.length,
                separatorBuilder: (_, _) => const SizedBox(height: 10),
                itemBuilder: (context, index) =>
                    _MemberCard(member: rows[index]),
              ),
            ),
    );
  }
}

class _MemberCard extends StatelessWidget {
  const _MemberCard({required this.member});

  final Membership member;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    // What they wrote, falling back to the account they signed up with.
    final name = member.answers['Name'] ?? member.userName ?? 'Someone';

    final rows = <(String, String)>[
      for (final entry in member.answers.entries)
        if (entry.key != 'Name')
          (
            entry.key,
            // The country was stored as a code; a reader wants the country.
            entry.key == 'Country'
                ? (Countries.nameOf(entry.value) ?? entry.value)
                : entry.value,
          ),
    ];

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              radius: 26,
              backgroundColor: theme.colorScheme.surfaceContainerHighest,
              backgroundImage: member.photoUrl == null
                  ? null
                  : NetworkImage(member.photoUrl!),
              child: member.photoUrl != null
                  ? null
                  : Text(name.characters.take(1).toString().toUpperCase()),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, style: theme.textTheme.titleMedium),
                  if (member.userName != null && member.userName != name)
                    Text(
                      'Signed in as ${member.userName}',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  const SizedBox(height: 8),
                  for (final (label, value) in rows)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SizedBox(
                            width: 96,
                            child: Text(
                              label,
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          ),
                          Expanded(
                            child: Text(
                              value,
                              style: theme.textTheme.bodyMedium,
                            ),
                          ),
                        ],
                      ),
                    ),
                  if (rows.isEmpty)
                    Text(
                      'Joined before the archive asked anything.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({
    required this.theme,
    required this.message,
    required this.detail,
  });

  final ThemeData theme;
  final String message;
  final String detail;

  @override
  Widget build(BuildContext context) => Center(
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
          Text(message, style: theme.textTheme.bodyLarge),
          const SizedBox(height: 8),
          Text(
            detail,
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
