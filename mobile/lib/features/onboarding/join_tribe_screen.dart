import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../providers/auth_provider.dart';
import '../../routing/app_router.dart';
import '../../providers/onboarding_provider.dart';
import 'join_scope_screen.dart';

/// The first question a new account is asked.
///
/// Somebody with no membership can see almost nothing, so landing them on an
/// empty home would be a worse first impression than asking once.
class JoinTribeScreen extends ConsumerWidget {
  const JoinTribeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => JoinScopeScreen(
    title: 'Find your family',
    intro:
        'Join the tribe your family belongs to. Until an administrator '
        'approves you, you will only see what is public.',
    searchLabel: 'Search tribes',
    emptyHint: 'No tribes have been created yet.',
    watch: (ref, query) => ref
        .watch(tribesProvider(query))
        .whenData((list) => list.map((t) => t.joinable).toList()),
    refresh: (ref, query) => ref.invalidate(tribesProvider(query)),
    // Offered once they have asked for a tribe: a clan is the next question
    // and there was previously nowhere to be asked it.
    footer: ref.watch(myMembershipsProvider).value?.isEmpty ?? true
        ? null
        : FilledButton.tonal(
            onPressed: () => context.push(Routes.joinClan),
            child: const Text('Next — join a clan'),
          ),
    actions: [
      TextButton(
        onPressed: () => ref.read(authProvider.notifier).signOut(),
        child: const Text('Sign out'),
      ),
    ],
  );
}
