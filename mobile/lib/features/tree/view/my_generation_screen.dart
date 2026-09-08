import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/person_summary.dart';
import '../../../providers/auth_provider.dart';
import '../../../providers/tree_provider.dart';
import '../../../routing/app_router.dart';

/// The line, said the way a family says it.
///
/// One name per generation from the top of the clan down to you, with both of
/// the numbers the clan counts by beside each — the older scale everybody
/// knows and the nearer one everybody uses. It ends where you are, because
/// that is the question it answers.
class MyGenerationScreen extends ConsumerWidget {
  const MyGenerationScreen({super.key, this.personUlid});

  /// Whose line. Defaults to the signed-in account's own record.
  final String? personUlid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    final mine = auth is AuthSignedIn ? auth.user.personUlid : null;
    final ulid = personUlid ?? mine;

    return Scaffold(
      appBar: AppBar(title: const Text('My generation'), centerTitle: true),
      body: ulid == null
          ? const _NotLinked()
          : _Line(ulid: ulid, isMe: personUlid == null || personUlid == mine),
    );
  }
}

class _Line extends ConsumerWidget {
  const _Line({required this.ulid, required this.isMe});

  final String ulid;
  final bool isMe;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final line = ref.watch(directLineProvider(ulid));

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(directLineProvider(ulid)),
      child: line.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => ListView(
          padding: const EdgeInsets.all(32),
          children: [
            Text(
              error is ApiException
                  ? error.message
                  : 'Could not read the line.',
              textAlign: TextAlign.center,
            ),
          ],
        ),
        data: (people) {
          if (people.isEmpty) {
            return ListView(
              padding: const EdgeInsets.fromLTRB(32, 80, 32, 32),
              children: [
                Text(
                  'Nobody is recorded above them yet.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyLarge,
                ),
              ],
            );
          }

          final standing = people.last.generation;

          return ListView(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 32),
            children: [
              _Header(
                outerOrigin: _outerOrigin(people),
                origin: standing?.origin ?? _origin(people),
              ),
              for (final (index, person) in people.indexed)
                _Row(
                  person: person,
                  // The last row is where the line stops, which is the reason
                  // anybody opened this screen.
                  isEnd: index == people.length - 1,
                  isMe: isMe && index == people.length - 1,
                  onTap: () => context.push(Routes.personPath(person.ulid)),
                ),
            ],
          );
        },
      ),
    );
  }

  /// The names the two columns are counted from, taken from whichever person
  /// in the line has them — every one of them carries the same pair.
  static String? _outerOrigin(List<PersonSummary> people) => people
      .map((p) => p.generation?.outerOrigin)
      .firstWhere((name) => name != null, orElse: () => null);

  static String? _origin(List<PersonSummary> people) => people
      .map((p) => p.generation?.origin)
      .firstWhere((name) => name != null, orElse: () => null);
}

class _Header extends StatelessWidget {
  const _Header({required this.outerOrigin, required this.origin});

  final String? outerOrigin;
  final String? origin;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = theme.textTheme.labelMedium?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
      fontWeight: FontWeight.w700,
    );

    return Padding(
      padding: const EdgeInsets.fromLTRB(10, 4, 10, 8),
      child: Row(
        children: [
          Expanded(
            flex: 3,
            child: Text(
              outerOrigin == null ? 'Generation' : 'From $outerOrigin',
              style: style,
            ),
          ),
          Expanded(
            flex: 3,
            child: Text(origin == null ? '' : 'From $origin', style: style),
          ),
          Expanded(flex: 4, child: Text('Name', style: style)),
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({
    required this.person,
    required this.isEnd,
    required this.isMe,
    required this.onTap,
  });

  final PersonSummary person;
  final bool isEnd;
  final bool isMe;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final standing = person.generation;

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 2),
      color: isEnd ? theme.colorScheme.primaryContainer : null,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
          child: Row(
            children: [
              Expanded(
                flex: 3,
                child: Text(
                  _ordinalOrDash(standing?.outerNumber),
                  style: theme.textTheme.bodyMedium,
                ),
              ),
              Expanded(
                flex: 3,
                child: Text(
                  // A dash, not a blank: above the origin the clan does not
                  // count, and saying nothing there reads as missing data.
                  _ordinalOrDash(standing?.number),
                  style: theme.textTheme.bodyMedium,
                ),
              ),
              Expanded(
                flex: 4,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      person.displayName,
                      style: theme.textTheme.titleSmall,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (isMe)
                      Text(
                        "I'M HERE",
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: theme.colorScheme.primary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _ordinalOrDash(int? number) =>
      number == null ? '—' : '${_ordinal(number)} generation';

  static String _ordinal(int n) => switch (n % 100) {
    11 || 12 || 13 => '${n}th',
    _ => switch (n % 10) {
      1 => '${n}st',
      2 => '${n}nd',
      3 => '${n}rd',
      _ => '${n}th',
    },
  };
}

/// An account with no record of its own has no line to show.
class _NotLinked extends StatelessWidget {
  const _NotLinked();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.person_search_outlined,
              size: 44,
              color: theme.colorScheme.onSurfaceVariant,
            ),
            const SizedBox(height: 16),
            Text(
              'Find yourself in the archive first',
              style: theme.textTheme.titleMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              'This is the line from the top of your clan down to you, so it '
              'needs to know which record is you.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: () => context.push(Routes.claimProfile),
              icon: const Icon(Icons.person_search_outlined),
              label: const Text('Find myself'),
            ),
          ],
        ),
      ),
    );
  }
}
