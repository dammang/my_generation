import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/committee.dart';
import '../../../providers/clan_provider.dart';

/// Who runs one clan, and how somebody joins them.
///
/// Direct appointments only. Somebody who administers this clan because they
/// run the tribe above it is not listed: they were not appointed here and
/// cannot be removed here, and showing them beside a Remove button that would
/// do nothing is worse than not showing them at all.
class CommitteeScreen extends ConsumerWidget {
  const CommitteeScreen({
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
