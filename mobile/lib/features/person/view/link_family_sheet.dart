import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/family_branch_summary.dart';
import '../../../models/person_summary.dart';
import '../../../widgets/person_tile.dart';
import '../../../providers/person_provider.dart';
import '../../../providers/review_provider.dart';
import '../../../widgets/form_banner.dart';

/// "Where did they come from?"
///
/// A spouse belongs to a line of their own, and the archive cannot work out
/// which — Margaret Whitfield is Edward's wife and also somebody else's
/// daughter, and only a person knows whose. Nothing is inferred: a wife is not
/// given her husband's generation, because that reads on the chart as a
/// recorded fact and is only ever an assumption.
///
/// Two ways to say it, and the first is the better one. If she is already in
/// the archive as somebody's daughter, claiming that record makes the two into
/// one person with both families — her parents, her siblings and her own
/// generation all arrive at once. Naming the family line alone says only which
/// family she came from.
///
/// Both propose rather than write. Attaching somebody to a family says they
/// are kin to everyone in it, and merging two records is the hardest thing in
/// an archive to undo in a family's understanding of itself.
class LinkFamilySheet extends ConsumerStatefulWidget {
  const LinkFamilySheet({
    super.key,
    required this.personUlid,
    required this.personName,
  });

  final String personUlid;
  final String personName;

  @override
  ConsumerState<LinkFamilySheet> createState() => _LinkFamilySheetState();
}

class _LinkFamilySheetState extends ConsumerState<LinkFamilySheet> {
  final _search = TextEditingController();

  Timer? _debounce;
  List<FamilyBranchSummary> _results = const [];
  List<PersonSummary> _people = const [];
  bool _loading = true;
  bool _submitting = false;
  String? _error;

  /// Searching for her own record, rather than for the family line.
  bool _byPerson = true;

  @override
  void initState() {
    super.initState();
    // The list opens populated: most archives have few families, and making
    // somebody type before seeing any of them hides how few there are.
    unawaited(_run(''));
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () => _run(value));
  }

  Future<void> _run(String query) async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final repository = ref.read(personRepositoryProvider);

      if (_byPerson) {
        final people = await repository.search(query);

        if (mounted) {
          setState(
            // Never herself: claiming a record as itself is the one answer
            // that cannot be right.
            () => _people = people
                .where((p) => p.ulid != widget.personUlid)
                .toList(growable: false),
          );
        }
      } else {
        final branches = await repository.familyBranches(query: query);

        if (mounted) setState(() => _results = branches);
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _claim(PersonSummary person) async {
    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await ref
          .read(reviewRepositoryProvider)
          .claimIdentity(
            personUlid: widget.personUlid,
            otherUlid: person.ulid,
            reason:
                '${widget.personName} and ${person.displayName} are the same '
                'person.',
          );

      if (!mounted) return;

      Navigator.of(context).pop();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Sent for review: ${widget.personName} is ${person.displayName}. '
            'Her family will confirm it.',
          ),
        ),
      );
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _submitting = false;
        });
      }
    }
  }

  Future<void> _link(FamilyBranchSummary branch) async {
    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await ref
          .read(reviewRepositoryProvider)
          .editPerson(
            ulid: widget.personUlid,
            changes: {'family_branch_ulid': branch.ulid},
            reason: '${widget.personName} came from the ${branch.name} family.',
          );

      if (!mounted) return;

      Navigator.of(context).pop();

      // Always a proposal, never an immediate write: the server enforces that
      // for family links whoever is asking, so there is no "saved" case to
      // report. "Sent" rather than "saved" is the difference between somebody
      // waiting for an answer and somebody finding out in a week that nothing
      // ever happened.
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Sent for review: linking ${widget.personName} to the '
            '${branch.name} family.',
          ),
        ),
      );
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _submitting = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          bottom: MediaQuery.viewInsetsOf(context).bottom + 20,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Where they came from', style: theme.textTheme.titleLarge),
            const SizedBox(height: 4),
            Text(
              _byPerson
                  ? 'If ${widget.personName} is already in the archive as '
                        'somebody\'s daughter, claim that record: the two '
                        'become one person with both families. Her family will '
                        'confirm it.'
                  : 'Which family did ${widget.personName} come from? Somebody '
                        'who administers it will confirm the link.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 12),
            SegmentedButton<bool>(
              segments: const [
                ButtonSegment(
                  value: true,
                  icon: Icon(Icons.person_search_outlined),
                  label: Text('Her own record'),
                ),
                ButtonSegment(
                  value: false,
                  icon: Icon(Icons.account_tree_outlined),
                  label: Text('Family line'),
                ),
              ],
              selected: {_byPerson},
              onSelectionChanged: _submitting
                  ? null
                  : (choice) {
                      setState(() => _byPerson = choice.first);
                      unawaited(_run(_search.text));
                    },
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _search,
              onChanged: _onChanged,
              enabled: !_submitting,
              decoration: InputDecoration(
                labelText: _byPerson ? 'Her name' : 'Family name',
                prefixIcon: const Icon(Icons.search),
                border: const OutlineInputBorder(),
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              FormBanner(
                message: _error!,
                tone: theme.colorScheme.error,
                icon: Icons.error_outline,
              ),
            ],
            const SizedBox(height: 8),
            if (_loading)
              const Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_byPerson)
              if (_people.isEmpty)
                Padding(
                  padding: const EdgeInsets.all(20),
                  child: Text(
                    _search.text.trim().length < 2
                        ? 'Type her name as her own family records it.'
                        : 'Nobody by that name is visible to you. Names are '
                              'matched from the beginning.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                )
              else
                Flexible(
                  child: ListView.builder(
                    shrinkWrap: true,
                    itemCount: _people.length,
                    itemBuilder: (context, i) => PersonTile(
                      person: _people[i],
                      onTap: _submitting ? null : () => _claim(_people[i]),
                    ),
                  ),
                )
            else if (_results.isEmpty)
              Padding(
                padding: const EdgeInsets.all(20),
                child: Text(
                  'No family by that name is visible to you. Names are matched '
                  'from the beginning.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              )
            else
              Flexible(
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: _results.length,
                  itemBuilder: (context, i) {
                    final branch = _results[i];

                    return ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.account_tree_outlined),
                      title: Text(branch.name),
                      subtitle: branch.subtitle.isEmpty
                          ? null
                          : Text(branch.subtitle),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: _submitting ? null : () => _link(branch),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}
