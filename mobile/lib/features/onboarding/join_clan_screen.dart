import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../models/joinable_scope.dart';
import '../../providers/onboarding_provider.dart';
import 'join_clan_form_screen.dart';
import 'join_scope_screen.dart';

/// Asking to join a clan, reached on its own.
///
/// Being in the tribe is not the same as being in the clan: somebody who has
/// chosen "my clan" is shown to clan members and to nobody else.
class JoinClanScreen extends StatelessWidget {
  const JoinClanScreen({super.key});

  @override
  Widget build(BuildContext context) => JoinScopeScreen(
    title: 'Join a clan',
    list: JoinScopeList(
      intro:
          'Ask to join the clan your family belongs to. Members see records '
          'kept within the clan; until you are approved you will only see '
          'what is public.',
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
