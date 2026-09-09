import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers/auth_provider.dart';
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
    actions: [
      TextButton(
        onPressed: () => ref.read(authProvider.notifier).signOut(),
        child: const Text('Sign out'),
      ),
    ],
  );
}
