import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/committee.dart';
import '../../../models/family_branch_summary.dart';
import '../../../models/person_summary.dart';
import '../../../providers/clan_provider.dart';
import '../../../routing/app_router.dart';

/// The page for a family this account administers.
///
/// Two things live here because they are the two things running a clan
/// actually means: where its tree begins, and who else runs it. An approved
/// clan is empty — no people, no starting point — and the Add Relative flow
/// has nothing to hang off until somebody records the ancestor.
///
/// The committee lists direct appointments only. Somebody who administers
/// this clan because they run the tribe above it is not listed: they were not
/// appointed here and cannot be removed here, and showing them beside a
/// Remove button that would do nothing is worse than not showing them at all.
class AdministerScreen extends ConsumerWidget {
  const AdministerScreen({
    super.key,
    required this.scopeType,
    required this.scopeUlid,
  });

  final String scopeType;
  final String scopeUlid;

  ScopeRef get _scope => (type: scopeType, ulid: scopeUlid);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final appointments = ref.watch(committeeProvider(_scope));

    // The scope's own name and what may be handed out here both come from the
    // administered list, so the screen is self-sufficient and a link to it
    // survives being opened cold from a notification.
    final scope = ref
        .watch(administeredScopesProvider)
        .value
        ?.where((s) => s.scopeUlid == scopeUlid)
        .firstOrNull;

    return Scaffold(
      appBar: AppBar(title: Text(scope?.name ?? 'Committee')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () =>
            _appoint(context, ref, scope?.assignableRoles ?? const []),
        icon: const Icon(Icons.person_add_alt),
        label: const Text('Appoint'),
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(committeeProvider(_scope));
          ref.invalidate(administeredScopesProvider);
          if (scopeType == 'clan') {
            ref.invalidate(clanProvider(scopeUlid));
          }
        },
        child: appointments.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => ListView(
            padding: const EdgeInsets.all(32),
            children: [
              Text(
                error is ApiException
                    ? error.message
                    : 'Could not read the committee.',
                textAlign: TextAlign.center,
              ),
            ],
          ),
          data: (all) => ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
            children: [
              // A tribe already has its families; only a clan begins empty.
              if (scopeType == 'clan') ...[
                _BeginsWith(clanUlid: scopeUlid),
                const SizedBox(height: 16),
                _FamilyBranches(clanUlid: scopeUlid),
                const SizedBox(height: 16),
                _Generations(clanUlid: scopeUlid),
                const SizedBox(height: 20),
                Text('Committee', style: theme.textTheme.titleMedium),
                const SizedBox(height: 6),
              ],
              Text(
                all.isEmpty
                    ? 'Nobody has been appointed here yet.'
                    : 'Appointed here. Anybody who runs the tribe above this '
                          'one also has authority here, and is not listed.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 12),
              for (final appointment in all)
                Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: theme.colorScheme.secondaryContainer,
                      child: Text(
                        _initials(appointment.userName),
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: theme.colorScheme.onSecondaryContainer,
                        ),
                      ),
                    ),
                    title: Text(appointment.userName),
                    subtitle: Text(
                      appointment.grantedBy == null
                          ? roleLabel(appointment.role)
                          : '${roleLabel(appointment.role)} · appointed by ${appointment.grantedBy}',
                    ),
                    trailing: IconButton(
                      tooltip: 'Remove',
                      icon: const Icon(Icons.person_remove_outlined),
                      onPressed: () => _revoke(context, ref, appointment),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _appoint(
    BuildContext context,
    WidgetRef ref,
    List<String> roles,
  ) async {
    if (roles.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('You cannot appoint anybody here.')),
      );
      return;
    }

    final candidate = await showModalBottomSheet<CommitteeCandidate>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _CandidateSheet(scope: _scope),
    );

    if (candidate == null || !context.mounted) return;

    final role = await showDialog<String>(
      context: context,
      builder: (context) => SimpleDialog(
        title: Text('What is ${candidate.userName} for this clan?'),
        children: [
          for (final role in roles)
            SimpleDialogOption(
              onPressed: () => Navigator.pop(context, role),
              child: ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(roleLabel(role)),
                // What the role lets somebody do, in the terms the person
                // appointing will be asked about later.
                subtitle: Text(roleDescription(role)),
                dense: true,
              ),
            ),
        ],
      ),
    );

    if (role == null || !context.mounted) return;

    try {
      await ref
          .read(clanRepositoryProvider)
          .appoint(
            scopeType: scopeType,
            scopeUlid: scopeUlid,
            userUlid: candidate.userUlid,
            role: role,
          );

      ref.invalidate(committeeProvider(_scope));

      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${candidate.userName} is now ${roleLabel(role).toLowerCase()}.',
            ),
          ),
        );
      }
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _revoke(
    BuildContext context,
    WidgetRef ref,
    Appointment appointment,
  ) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Remove ${appointment.userName}?'),
        content: Text(
          'They will keep their membership and everything they have already '
          'contributed, but will no longer act as '
          '${roleLabel(appointment.role).toLowerCase()} here.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Remove'),
          ),
        ],
      ),
    );

    if (!(ok ?? false) || !context.mounted) return;

    try {
      await ref
          .read(clanRepositoryProvider)
          .revoke(
            scopeType: scopeType,
            scopeUlid: scopeUlid,
            userUlid: appointment.userUlid,
            role: appointment.role,
          );

      ref.invalidate(committeeProvider(_scope));
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }
}

/// Where the clan's tree begins.
///
/// The first card on the page because an approved clan has nothing in it, and
/// nothing else the founder can do here matters until this is answered: every
/// other screen that adds a person adds them *relative to* somebody.
class _BeginsWith extends ConsumerWidget {
  const _BeginsWith({required this.clanUlid});

  final String clanUlid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final clan = ref.watch(clanProvider(clanUlid));

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Where this family begins',
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            clan.when(
              loading: () => const LinearProgressIndicator(),
              error: (error, _) => Text(
                error is ApiException
                    ? error.message
                    : 'Could not read this clan.',
                style: theme.textTheme.bodyMedium,
              ),
              data: (clan) => clan.hasAncestor
                  ? Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.account_tree_outlined),
                          title: Text(
                            clan.ancestorName ?? 'The founding ancestor',
                          ),
                          subtitle: const Text('The clan descends from here'),
                          trailing: const Icon(Icons.chevron_right),
                          // Their own record, where Add Relative lives: the
                          // tree is built downward from this one person.
                          onTap: () => context.push(
                            Routes.personPath(clan.ancestorUlid!),
                          ),
                        ),
                        const Divider(height: 8),
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(Icons.filter_1_outlined),
                          title: Text(
                            clan.originName ?? 'Not set — counted from the top',
                          ),
                          // Two different people, and the difference is the
                          // point: numbering from an ancestor eleven
                          // generations back makes every number too large to
                          // mean anything to the family using it.
                          subtitle: Text(
                            clan.hasOrigin
                                ? _countedFrom(clan)
                                : 'Tap to count generations from somebody later',
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => _setOrigin(context, ref, clan.name),
                        ),
                      ],
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Nobody yet. Start with the ancestor the clan '
                          'descends from — the oldest person anybody still '
                          'remembers — and add their children from there.',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 12),
                        FilledButton.icon(
                          onPressed: () => _startTree(context, ref, clan.name),
                          icon: const Icon(Icons.add),
                          label: const Text('Start the family tree'),
                          style: FilledButton.styleFrom(
                            minimumSize: const Size.fromHeight(48),
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

  /// "Generations are counted from here · 11th from Pu Zo".
  static String _countedFrom(ClanDetail clan) {
    final offset = clan.generationOffset;

    if (offset == null || clan.ancestorName == null) {
      return 'Generations are counted from here';
    }

    return 'Generations are counted from here · '
        '${_ordinal(offset)} from ${clan.ancestorName}';
  }

  /// Choosing where the numbering starts.
  ///
  /// People above the origin are not renumbered downward — they become the
  /// pre-generations, which is what a family calls the ancestors it counts
  /// back to but not from.
  Future<void> _setOrigin(
    BuildContext context,
    WidgetRef ref,
    String clanName,
  ) async {
    final person = await showModalBottomSheet<PersonSummary>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _ClanPersonSheet(
        clanUlid: clanUlid,
        title: 'Count the generations of $clanName from',
      ),
    );

    if (person == null || !context.mounted) return;

    try {
      await ref
          .read(clanRepositoryProvider)
          .setCountingOrigin(clanUlid: clanUlid, personUlid: person.ulid);

      ref.invalidate(clanProvider(clanUlid));

      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${person.displayName} is now the first generation. '
              'Everybody above them becomes a pre-generation.',
            ),
          ),
        );
      }
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _startTree(
    BuildContext context,
    WidgetRef ref,
    String clanName,
  ) async {
    final ancestor = await showModalBottomSheet<_NewAncestor>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _AncestorSheet(clanName: clanName),
    );

    if (ancestor == null || !context.mounted) return;

    try {
      final personUlid = await ref
          .read(clanRepositoryProvider)
          .startTreeWith(
            clanUlid: clanUlid,
            displayName: ancestor.name,
            gender: ancestor.gender,
            birth: ancestor.birth,
          );

      ref.invalidate(clanProvider(clanUlid));

      if (context.mounted) {
        // Straight to their record, because the next thing anybody wants is
        // to add their children, and that lives on the person's own page.
        context.push(Routes.personPath(personUlid));
      }
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }
}

/// What the sheet collected.
class _NewAncestor {
  const _NewAncestor({required this.name, this.gender, this.birth});

  final String name;
  final String? gender;
  final String? birth;
}

/// The first person in a clan.
///
/// A name alone is enough on purpose. Oral genealogy routinely records
/// somebody by one name and a relationship and nothing else, and asking for a
/// birth date before accepting a name is how an archive refuses its own
/// material.
class _AncestorSheet extends StatefulWidget {
  const _AncestorSheet({required this.clanName});

  final String clanName;

  @override
  State<_AncestorSheet> createState() => _AncestorSheetState();
}

class _AncestorSheetState extends State<_AncestorSheet> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _birth = TextEditingController();

  String? _gender;

  @override
  void dispose() {
    _name.dispose();
    _birth.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    Navigator.pop(
      context,
      _NewAncestor(
        name: _name.text.trim(),
        gender: _gender,
        birth: _birth.text.trim(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 24,
      ),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'The ancestor ${widget.clanName} descends from',
              style: theme.textTheme.titleLarge,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _name,
              autofocus: true,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                labelText: 'Name',
                helperText: 'As the family says it. One name is enough.',
              ),
              validator: (value) =>
                  (value ?? '').trim().isEmpty ? 'Give them a name.' : null,
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _gender,
              decoration: const InputDecoration(labelText: 'Gender (optional)'),
              items: const [
                DropdownMenuItem(value: 'male', child: Text('Male')),
                DropdownMenuItem(value: 'female', child: Text('Female')),
                DropdownMenuItem(value: 'unknown', child: Text('Not known')),
              ],
              onChanged: (value) => setState(() => _gender = value),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _birth,
              decoration: const InputDecoration(
                labelText: 'Born (optional)',
                // The parser keeps the wording and derives what it can, so
                // "abt. 1890" is a better record than a date nobody knows.
                helperText: 'Say it however it is remembered: "abt. 1890".',
              ),
            ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _submit,
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
              ),
              child: const Text('Start the tree here'),
            ),
          ],
        ),
      ),
    );
  }
}

/// The named lines inside a clan, and what each one counts from.
///
/// This is what actually produces generation numbers. A clan ancestor says
/// where the family begins; a branch's ancestor is what the counter walks down
/// from, and people who belong to no branch carry no generation at all however
/// much has been computed about them.
class _FamilyBranches extends ConsumerWidget {
  const _FamilyBranches({required this.clanUlid});

  final String clanUlid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final branches = ref.watch(clanBranchesProvider(clanUlid));

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Family branches', style: theme.textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(
              'Generations are counted from where each line starts.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 8),
            branches.when(
              loading: () => const LinearProgressIndicator(),
              error: (error, _) => Text(
                error is ApiException
                    ? error.message
                    : 'Could not read the family branches.',
                style: theme.textTheme.bodyMedium,
              ),
              data: (all) => Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final branch in all)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.share_outlined),
                      title: Text(branch.name),
                      // A line with no starting point counts nobody, and
                      // saying so is the difference between a screen that
                      // looks finished and one that asks for the missing half.
                      subtitle: Text(
                        branch.ancestorName == null
                            ? 'No starting point — nobody is counted yet'
                            : 'Counted from ${branch.ancestorName}',
                        style: branch.ancestorName == null
                            ? TextStyle(color: theme.colorScheme.error)
                            : null,
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => _setAncestor(context, ref, branch),
                    ),
                  const SizedBox(height: 4),
                  OutlinedButton.icon(
                    onPressed: () => _addBranch(context, ref),
                    icon: const Icon(Icons.add),
                    label: const Text('Add a family branch'),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(46),
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

  Future<void> _addBranch(BuildContext context, WidgetRef ref) async {
    final clan = ref.read(clanProvider(clanUlid)).value;
    final tribeUlid = clan?.tribeUlid;

    if (tribeUlid == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Still reading the clan. Try again.')),
      );
      return;
    }

    final name = await showDialog<String>(
      context: context,
      builder: (_) => const _BranchNameDialog(),
    );

    if (name == null || !context.mounted) return;

    final ancestor = await _pickPerson(context, 'Who does $name start from?');

    if (!context.mounted) return;

    await _run(
      context,
      () => ref
          .read(clanRepositoryProvider)
          .createBranch(
            tribeUlid: tribeUlid,
            clanUlid: clanUlid,
            name: name,
            ancestorUlid: ancestor?.ulid,
          ),
      ref,
    );
  }

  Future<void> _setAncestor(
    BuildContext context,
    WidgetRef ref,
    FamilyBranchSummary branch,
  ) async {
    final person = await _pickPerson(
      context,
      'Who does ${branch.name} start from?',
    );

    if (person == null || !context.mounted) return;

    await _run(
      context,
      () => ref
          .read(clanRepositoryProvider)
          .setBranchAncestor(
            branchUlid: branch.ulid,
            ancestorUlid: person.ulid,
          ),
      ref,
    );
  }

  Future<PersonSummary?> _pickPerson(BuildContext context, String title) =>
      showModalBottomSheet<PersonSummary>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (_) => _ClanPersonSheet(clanUlid: clanUlid, title: title),
      );

  /// Reports what happened to the archive, not that a row was written.
  Future<void> _run(
    BuildContext context,
    Future<int> Function() work,
    WidgetRef ref,
  ) async {
    try {
      final placed = await work();

      ref.invalidate(clanBranchesProvider(clanUlid));

      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              placed == 0
                  ? 'Saved. Nobody new was counted from there.'
                  : placed == 1
                  ? '1 person is now counted from there.'
                  : '$placed people are now counted from there.',
            ),
          ),
        );
      }
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }
}

/// Naming a line. One field, so a dialog rather than a screen.
class _BranchNameDialog extends StatefulWidget {
  const _BranchNameDialog();

  @override
  State<_BranchNameDialog> createState() => _BranchNameDialogState();
}

class _BranchNameDialogState extends State<_BranchNameDialog> {
  final _name = TextEditingController();

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('New family branch'),
    content: TextField(
      controller: _name,
      autofocus: true,
      textCapitalization: TextCapitalization.words,
      decoration: const InputDecoration(
        labelText: 'Name of the line',
        helperText: 'Often the founder\'s name, or where they settled.',
      ),
      onSubmitted: (value) => Navigator.pop(context, value.trim()),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: () {
          final name = _name.text.trim();
          if (name.isNotEmpty) Navigator.pop(context, name);
        },
        child: const Text('Next'),
      ),
    ],
  );
}

/// Somebody already in this clan.
///
/// Restricted to the clan on purpose: a line inside the Guite cannot begin
/// with somebody who is not one, and the server refuses it anyway.
class _ClanPersonSheet extends ConsumerStatefulWidget {
  const _ClanPersonSheet({required this.clanUlid, required this.title});

  final String clanUlid;
  final String title;

  @override
  ConsumerState<_ClanPersonSheet> createState() => _ClanPersonSheetState();
}

class _ClanPersonSheetState extends ConsumerState<_ClanPersonSheet> {
  final _search = TextEditingController();

  Timer? _debounce;
  String _query = '';

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 350),
      () => setState(() => _query = value.trim()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final people = ref.watch(
      clanPeopleProvider((clan: widget.clanUlid, query: _query)),
    );

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 20,
      ),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.7,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.title, style: theme.textTheme.titleLarge),
            const SizedBox(height: 12),
            TextField(
              controller: _search,
              onChanged: _onChanged,
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.search),
                hintText: 'Search by name',
              ),
            ),
            const SizedBox(height: 12),
            Expanded(
              child: people.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => Center(
                  child: Text(
                    error is ApiException
                        ? error.message
                        : 'Could not read this clan\'s people.',
                    textAlign: TextAlign.center,
                  ),
                ),
                data: (all) => all.isEmpty
                    ? Center(
                        child: Text(
                          _query.isEmpty
                              ? 'Nobody in this clan yet.'
                              : 'Nobody found. Names match from the beginning.',
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      )
                    : ListView.builder(
                        itemCount: all.length,
                        itemBuilder: (context, i) => ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(all[i].displayName),
                          subtitle: all[i].birthDisplay == null
                              ? null
                              : Text(all[i].birthDisplay!),
                          onTap: () => Navigator.pop(context, all[i]),
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Generation labels this clan has named.
///
/// Numbers are normally counted, not typed — this exists for the cases
/// counting cannot reach: somebody who married in, or a family whose own
/// numbering starts somewhere the graph does not know about. A label has to
/// exist before anybody can be assigned to it, which is why it is made here
/// rather than buried in one person's edit form.
class _Generations extends ConsumerWidget {
  const _Generations({required this.clanUlid});

  final String clanUlid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final clan = ref.watch(clanProvider(clanUlid)).value;
    final tribeUlid = clan?.tribeUlid;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Generation labels', style: theme.textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(
              'Most people are numbered automatically. These are for the ones '
              'who cannot be — somebody who married in, for instance.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 8),
            if (tribeUlid == null)
              const LinearProgressIndicator()
            else
              Consumer(
                builder: (context, ref, _) {
                  final generations = ref.watch(generationsProvider(tribeUlid));

                  return generations.when(
                    loading: () => const LinearProgressIndicator(),
                    error: (error, _) => Text(
                      error is ApiException
                          ? error.message
                          : 'Could not read the generation labels.',
                      style: theme.textTheme.bodyMedium,
                    ),
                    data: (all) => Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (all.isNotEmpty)
                          Wrap(
                            spacing: 8,
                            runSpacing: 4,
                            children: [
                              for (final generation in all)
                                Chip(
                                  label: Text(generation.label),
                                  visualDensity: VisualDensity.compact,
                                ),
                            ],
                          ),
                        const SizedBox(height: 8),
                        OutlinedButton.icon(
                          onPressed: () =>
                              _add(context, ref, tribeUlid, all.length + 1),
                          icon: const Icon(Icons.add),
                          label: const Text('Add a generation'),
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size.fromHeight(46),
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _add(
    BuildContext context,
    WidgetRef ref,
    String tribeUlid,
    int suggested,
  ) async {
    final made = await showDialog<({int number, String name})>(
      context: context,
      builder: (_) => _GenerationDialog(suggested: suggested),
    );

    if (made == null || !context.mounted) return;

    try {
      await ref
          .read(clanRepositoryProvider)
          .createGeneration(
            tribeUlid: tribeUlid,
            clanUlid: clanUlid,
            number: made.number,
            name: made.name.isEmpty ? null : made.name,
          );

      ref.invalidate(generationsProvider(tribeUlid));
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }
}

class _GenerationDialog extends StatefulWidget {
  const _GenerationDialog({required this.suggested});

  final int suggested;

  @override
  State<_GenerationDialog> createState() => _GenerationDialogState();
}

class _GenerationDialogState extends State<_GenerationDialog> {
  late final _number = TextEditingController(text: '${widget.suggested}');
  late final _name = TextEditingController(
    text: '${_ordinal(widget.suggested)} Generation',
  );

  @override
  void dispose() {
    _number.dispose();
    _name.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('New generation label'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        TextField(
          controller: _number,
          keyboardType: TextInputType.number,
          decoration: const InputDecoration(labelText: 'Number'),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _name,
          decoration: const InputDecoration(
            labelText: 'Name',
            // Every tribe has its own word for a generation, and forcing an
            // English ordinal on one that does not use them is the archive
            // overwriting the thing it exists to record.
            helperText: 'Your own word for it, if you have one.',
          ),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: () {
          final number = int.tryParse(_number.text.trim());

          if (number != null) {
            Navigator.pop(context, (number: number, name: _name.text.trim()));
          }
        },
        child: const Text('Add'),
      ),
    ],
  );
}

String _ordinal(int n) => switch (n % 100) {
  11 || 12 || 13 => '${n}th',
  _ => switch (n % 10) {
    1 => '${n}st',
    2 => '${n}nd',
    3 => '${n}rd',
    _ => '${n}th',
  },
};

/// Who could be appointed.
///
/// The pool reaches above the clan on purpose: memberships in this archive are
/// held at the tribe, so a list of the clan's own members would be empty and
/// nobody could ever be appointed.
class _CandidateSheet extends ConsumerStatefulWidget {
  const _CandidateSheet({required this.scope});

  final ScopeRef scope;

  @override
  ConsumerState<_CandidateSheet> createState() => _CandidateSheetState();
}

class _CandidateSheetState extends ConsumerState<_CandidateSheet> {
  final _search = TextEditingController();

  Timer? _debounce;
  String _query = '';

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 350),
      () => setState(() => _query = value.trim()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final candidates = ref.watch(
      committeeCandidatesProvider((scope: widget.scope, query: _query)),
    );

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 20,
      ),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.7,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Appoint somebody', style: theme.textTheme.titleLarge),
            const SizedBox(height: 12),
            TextField(
              controller: _search,
              autofocus: true,
              onChanged: _onChanged,
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.search),
                hintText: 'Search by name',
              ),
            ),
            const SizedBox(height: 12),
            Expanded(
              child: candidates.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => Center(
                  child: Text(
                    error is ApiException
                        ? error.message
                        : 'Could not read who is eligible.',
                    textAlign: TextAlign.center,
                  ),
                ),
                data: (all) {
                  if (all.isEmpty) {
                    return Center(
                      child: Text(
                        _query.isEmpty
                            ? 'Nobody in this family has an account yet.'
                            : 'Nobody found. Names match from the beginning.',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    );
                  }

                  return ListView.builder(
                    itemCount: all.length,
                    itemBuilder: (context, i) {
                      final candidate = all[i];

                      return ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: CircleAvatar(
                          child: Text(_initials(candidate.userName)),
                        ),
                        title: Text(candidate.userName),
                        // What they already hold here, so appointing somebody
                        // twice is visibly a second role rather than a repeat.
                        subtitle: candidate.roles.isEmpty
                            ? null
                            : Text(candidate.roles.map(roleLabel).join(', ')),
                        onTap: () => Navigator.pop(context, candidate),
                      );
                    },
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

String _initials(String name) {
  final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty);

  if (parts.isEmpty) return '?';

  return parts.take(2).map((p) => p[0].toUpperCase()).join();
}
