import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/constants/countries.dart';
import '../../../core/errors/api_exception.dart';
import '../../../models/committee.dart';
import '../../../models/membership.dart';
import '../../../providers/clan_provider.dart';
import '../../../providers/onboarding_provider.dart';
import '../export/member_roll.dart';

/// Everybody in a family you run, and what they told us about themselves.
///
/// A roll rather than a queue: the join requests page empties as it is worked
/// through, this one stays. A table because it is read down a column — who is
/// in Malaysia, whose father was Thawng Dam — and a list of cards cannot be
/// read that way.
class MembersScreen extends ConsumerStatefulWidget {
  const MembersScreen({super.key});

  @override
  ConsumerState<MembersScreen> createState() => _MembersScreenState();
}

class _MembersScreenState extends ConsumerState<MembersScreen> {
  AdministeredScope? _clan;
  String? _country;
  bool _exporting = false;

  Future<void> _export(
    List<Membership> members,
    String title, {
    required bool asDocument,
  }) async {
    setState(() => _exporting = true);

    final messenger = ScaffoldMessenger.of(context);
    final roll = MemberRoll(members: members, title: title);

    try {
      if (asDocument) {
        await roll.shareDocument();
      } else {
        await roll.shareSpreadsheet();
      }
    } catch (error) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            error is ApiException ? error.message : 'Could not export. $error',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scopes = ref.watch(administeredScopesProvider).value ?? const [];
    final clan = _clan ?? (scopes.isEmpty ? null : scopes.first);

    if (scopes.isEmpty || clan == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Members')),
        body: _Empty(
          theme: theme,
          message: 'You do not run a family yet.',
          detail: 'Members appear here for the clans you administer.',
        ),
      );
    }

    final key = (type: clan.scopeType, ulid: clan.scopeUlid);
    final all =
        ref.watch(scopeMembersProvider(key)).value ?? const <Membership>[];

    final shown = _country == null
        ? all
        : all.where((m) => m.country?.toUpperCase() == _country).toList();

    final title = [
      clan.name,
      if (_country != null) Countries.nameOf(_country) ?? _country!,
    ].join(' · ');

    return Scaffold(
      appBar: AppBar(
        title: const Text('Members'),
        actions: [
          if (_exporting)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 18),
              child: Center(
                child: SizedBox(
                  height: 18,
                  width: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            )
          else
            PopupMenuButton<bool>(
              icon: const Icon(Icons.ios_share),
              tooltip: 'Export',
              enabled: shown.isNotEmpty,
              onSelected: (asDocument) =>
                  _export(shown, title, asDocument: asDocument),
              itemBuilder: (context) => const [
                PopupMenuItem(
                  value: true,
                  child: ListTile(
                    leading: Icon(Icons.picture_as_pdf_outlined),
                    title: Text('PDF'),
                    subtitle: Text('To read and print'),
                  ),
                ),
                PopupMenuItem(
                  value: false,
                  child: ListTile(
                    leading: Icon(Icons.table_chart_outlined),
                    title: Text('Spreadsheet'),
                    subtitle: Text('Opens in Excel or Sheets'),
                  ),
                ),
              ],
            ),
        ],
      ),
      body: Column(
        children: [
          _Filters(
            scopes: scopes,
            clan: clan,
            country: _country,
            // Only the countries these members are actually in. A list of two
            // hundred and forty-nine of which four are used is one nobody
            // scrolls to the bottom of.
            countries: Countries.only(all.map((m) => m.country)),
            onClan: (chosen) => setState(() {
              _clan = chosen;
              _country = null;
            }),
            onCountry: (chosen) => setState(() => _country = chosen),
          ),
          Expanded(
            child: shown.isEmpty
                ? _Empty(
                    theme: theme,
                    message: _country == null
                        ? 'Nobody has joined ${clan.name} yet.'
                        : 'Nobody here is in that country.',
                    detail: 'Approved requests appear here.',
                  )
                : RefreshIndicator(
                    onRefresh: () async =>
                        ref.invalidate(scopeMembersProvider(key)),
                    child: _Table(members: shown),
                  ),
          ),
        ],
      ),
    );
  }
}

class _Filters extends StatelessWidget {
  const _Filters({
    required this.scopes,
    required this.clan,
    required this.country,
    required this.countries,
    required this.onClan,
    required this.onCountry,
  });

  final List<AdministeredScope> scopes;
  final AdministeredScope clan;
  final String? country;
  final Map<String, String> countries;
  final ValueChanged<AdministeredScope> onClan;
  final ValueChanged<String?> onCountry;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
    child: Row(
      children: [
        Expanded(
          child: DropdownButtonFormField<String>(
            initialValue: clan.scopeUlid,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Clan',
              isDense: true,
              border: OutlineInputBorder(),
            ),
            items: [
              for (final scope in scopes)
                DropdownMenuItem(
                  value: scope.scopeUlid,
                  child: Text(scope.name, overflow: TextOverflow.ellipsis),
                ),
            ],
            onChanged: (ulid) =>
                onClan(scopes.firstWhere((s) => s.scopeUlid == ulid)),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: DropdownButtonFormField<String?>(
            initialValue: country,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Country',
              isDense: true,
              border: OutlineInputBorder(),
            ),
            items: [
              const DropdownMenuItem(value: null, child: Text('Anywhere')),
              for (final entry in countries.entries)
                DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value, overflow: TextOverflow.ellipsis),
                ),
            ],
            onChanged: onCountry,
          ),
        ),
      ],
    ),
  );
}

/// The roll itself.
///
/// Scrolls both ways: nine columns will not fit the width of a phone, and
/// squeezing them until they do makes every one unreadable.
class _Table extends StatelessWidget {
  const _Table({required this.members});

  final List<Membership> members;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    child: SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        headingRowHeight: 40,
        dataRowMinHeight: 58,
        dataRowMaxHeight: 72,
        columnSpacing: 22,
        columns: const [
          DataColumn(label: Text('Photo')),
          DataColumn(label: Text('Name')),
          DataColumn(label: Text('Parents')),
          DataColumn(label: Text('Grandparents')),
          DataColumn(label: Text('Country')),
          DataColumn(label: Text('Contact')),
          DataColumn(label: Text('Joined')),
        ],
        rows: [
          for (final member in members)
            DataRow(
              cells: [
                DataCell(_Photo(member: member)),
                DataCell(_TwoLines(first: member.name, second: member.email)),
                DataCell(
                  _TwoLines(
                    first: member.fatherName,
                    second: member.motherName,
                  ),
                ),
                DataCell(
                  _TwoLines(
                    first: member.grandfatherName,
                    second: member.grandmotherName,
                  ),
                ),
                DataCell(
                  Text(
                    Countries.nameOf(member.country) ?? member.country ?? '—',
                  ),
                ),
                DataCell(Text(member.contact ?? '—')),
                DataCell(Text(MemberRoll.day(member.joinedAt))),
              ],
            ),
        ],
      ),
    ),
  );
}

class _Photo extends StatelessWidget {
  const _Photo({required this.member});

  final Membership member;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (member.photoUrl == null) {
      return CircleAvatar(
        radius: 25,
        backgroundColor: theme.colorScheme.surfaceContainerHighest,
        child: Text(member.name.characters.take(1).toString().toUpperCase()),
      );
    }

    return InkWell(
      // A thumbnail of a face is not enough to recognise somebody by, which is
      // the only reason it was asked for.
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute<void>(
          fullscreenDialog: true,
          builder: (_) => _FullScreenPhoto(member: member),
        ),
      ),
      child: CircleAvatar(
        radius: 25,
        backgroundColor: theme.colorScheme.surfaceContainerHighest,
        backgroundImage: NetworkImage(member.photoUrl!),
      ),
    );
  }
}

/// The photograph, filling the screen.
///
/// Dark and chromeless: a face is what is being looked at, and a dialog's
/// border and background are just things in the way of it.
class _FullScreenPhoto extends StatelessWidget {
  const _FullScreenPhoto({required this.member});

  final Membership member;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.black,
    appBar: AppBar(
      backgroundColor: Colors.black,
      foregroundColor: Colors.white,
      title: Text(member.name),
      elevation: 0,
    ),
    body: Center(
      child: InteractiveViewer(
        minScale: 1,
        maxScale: 5,
        child: Image.network(
          member.photoUrl!,
          fit: BoxFit.contain,
          width: double.infinity,
          errorBuilder: (context, _, _) => const Padding(
            padding: EdgeInsets.all(24),
            child: Text(
              'The photograph could not be loaded. The link it was fetched '
              'with is short-lived; go back and open it again.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white70),
            ),
          ),
          loadingBuilder: (context, child, progress) => progress == null
              ? child
              : const Center(child: CircularProgressIndicator()),
        ),
      ),
    ),
  );
}

/// Two facts stacked, the second quieter than the first.
class _TwoLines extends StatelessWidget {
  const _TwoLines({required this.first, this.second});

  final String? first;
  final String? second;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(first ?? '—', maxLines: 1, overflow: TextOverflow.ellipsis),
        if (second != null && second!.isNotEmpty)
          Text(
            second!,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
      ],
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
