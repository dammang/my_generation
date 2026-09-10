import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/joinable_scope.dart';
import '../../providers/auth_provider.dart';
import '../../providers/onboarding_provider.dart';
import 'join_clan_form_screen.dart';
import 'join_scope_screen.dart';

/// The first and only question a new account is asked.
///
/// The clan, not the tribe. A tribe is an administrative container — it holds
/// clans and it sets defaults — and joining one gave a member nothing they did
/// not get from their clan. Being approved into a clan now carries its tribe
/// with it, because nobody joins the Zomi in order to join JK.
class JoinScreen extends ConsumerWidget {
  const JoinScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => JoinScopeScreen(
    title: 'Find your family',
    actions: [
      TextButton(
        onPressed: () => ref.read(authProvider.notifier).signOut(),
        child: const Text('Sign out'),
      ),
    ],
    list: JoinScopeList(
      intro:
          'Ask to join the clan your family belongs to. Somebody in that '
          'family reviews it; until then you will only see what is public.',
      searchLabel: 'Search clans',
      emptyHint: 'No clans have been created yet.',
      watch: _watch,
      refresh: _refresh,
      onAsk: (context, clan) => Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => JoinClanFormScreen(clan: clan)),
      ),
    ),
  );
}

AsyncValue<List<JoinableScope>> _watch(WidgetRef ref, String query) => ref
    .watch(joinableClansProvider(query))
    .whenData((list) => list.map((clan) => clan.joinable).toList());

void _refresh(WidgetRef ref, String query) =>
    ref.invalidate(joinableClansProvider(query));
