import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/joinable_scope.dart';
import '../../providers/auth_provider.dart';
import '../../providers/onboarding_provider.dart';
import 'join_clan_form_screen.dart';
import 'join_scope_screen.dart';

/// The first question a new account is asked.
///
/// Both doors on one screen. A person knows their clan — "I'm JK" — long
/// before they think of themselves as belonging to a tribe, and making them
/// find the tribe first is how the clan came to be unreachable: this screen is
/// where every new account is held, and it only ever listed tribes.
class JoinScreen extends ConsumerWidget {
  const JoinScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => DefaultTabController(
    // Clans first: it is the answer most people have ready.
    initialIndex: 0,
    length: 2,
    child: Scaffold(
      appBar: AppBar(
        title: const Text('Find your family'),
        actions: [
          TextButton(
            onPressed: () => ref.read(authProvider.notifier).signOut(),
            child: const Text('Sign out'),
          ),
        ],
        bottom: const TabBar(
          tabs: [
            Tab(text: 'Clans'),
            Tab(text: 'Tribes'),
          ],
        ),
      ),
      body: TabBarView(
        children: [
          JoinScopeList(
            intro:
                'Ask to join the clan your family belongs to. Somebody in '
                'that family reviews it; until then you will only see what '
                'is public.',
            searchLabel: 'Search clans',
            emptyHint: 'No clans have been created yet.',
            watch: (ref, query) => ref
                .watch(joinableClansProvider(query))
                .whenData((list) => list.map((c) => c.joinable).toList()),
            refresh: (ref, query) =>
                ref.invalidate(joinableClansProvider(query)),
            onAsk: _askForClan,
          ),
          JoinScopeList(
            intro:
                'Or join the whole tribe, if you are not sure which clan '
                'yours is. An administrator reviews it.',
            searchLabel: 'Search tribes',
            emptyHint: 'No tribes have been created yet.',
            watch: (ref, query) => ref
                .watch(tribesProvider(query))
                .whenData((list) => list.map((t) => t.joinable).toList()),
            refresh: (ref, query) => ref.invalidate(tribesProvider(query)),
          ),
        ],
      ),
    ),
  );
}

void _askForClan(BuildContext context, JoinableScope clan) => Navigator.of(
  context,
).push(MaterialPageRoute<void>(builder: (_) => JoinClanFormScreen(clan: clan)));
