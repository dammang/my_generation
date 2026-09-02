import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/errors/api_exception.dart';
import '../../models/person_summary.dart';
import '../../providers/auth_provider.dart';
import '../../providers/onboarding_provider.dart';
import '../../widgets/form_banner.dart';
import '../../widgets/person_tile.dart';

/// "Which of these people is you?"
///
/// The record usually exists before the person does — an uncle added them years
/// ago. Claiming is a request, not an action: being recognised as a person also
/// makes you close kin of everyone around them, so it is somebody else's to
/// approve.
class ClaimProfileScreen extends ConsumerStatefulWidget {
  const ClaimProfileScreen({super.key});

  @override
  ConsumerState<ClaimProfileScreen> createState() => _ClaimProfileScreenState();
}

class _ClaimProfileScreenState extends ConsumerState<ClaimProfileScreen> {
  final _search = TextEditingController();
  final _statement = TextEditingController();

  Timer? _debounce;
  List<PersonSummary> _results = const [];
  PersonSummary? _selected;
  bool _searching = false;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _statement.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () => _run(value));
  }

  Future<void> _run(String query) async {
    if (query.trim().length < 2) {
      setState(() => _results = const []);
      return;
    }

    setState(() {
      _searching = true;
      _error = null;
    });

    try {
      final people = await ref.read(onboardingRepositoryProvider).searchPeople(query);
      if (mounted) setState(() => _results = people);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  Future<void> _submit() async {
    final person = _selected;
    if (person == null) return;

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await ref.read(onboardingRepositoryProvider).claimProfile(
            personUlid: person.ulid,
            statement: _statement.text.trim(),
          );

      ref.invalidate(myClaimsProvider);

      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Asked to be recognised as ${person.displayName}. '
              'Someone in your family will confirm it.',
            ),
          ),
        );
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = ref.watch(authProvider);
    final alreadyLinked = auth is AuthSignedIn && auth.user.hasClaimedPerson;

    return Scaffold(
      appBar: AppBar(title: const Text('This is me')),
      body: SafeArea(
        child: alreadyLinked
            ? _AlreadyLinked(name: auth.user.personName ?? 'a person')
            : Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(
                          'Search for yourself in your family archive. You will only '
                          'see people you are already able to view.',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(height: 16),
                        TextField(
                          controller: _search,
                          onChanged: _onChanged,
                          textInputAction: TextInputAction.search,
                          decoration: InputDecoration(
                            labelText: 'Your name',
                            prefixIcon: const Icon(Icons.search),
                            suffixIcon: _searching
                                ? const Padding(
                                    padding: EdgeInsets.all(14),
                                    child: SizedBox(
                                      height: 18, width: 18,
                                      child: CircularProgressIndicator(strokeWidth: 2.2),
                                    ),
                                  )
                                : null,
                          ),
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 14),
                          FormBanner(message: _error!, tone: theme.colorScheme.error),
                        ],
                      ],
                    ),
                  ),
                  Expanded(child: _results.isEmpty ? _hint(theme) : _list()),
                  if (_selected != null) _confirmBar(theme),
                ],
              ),
      ),
    );
  }

  Widget _hint(ThemeData theme) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Text(
            _search.text.trim().length < 2
                ? 'Start typing your name.'
                : 'Nobody matching that name is visible to you yet.',
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyLarge?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ),
      );

  Widget _list() => ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
        itemCount: _results.length,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final person = _results[index];

          return PersonTile(
            person: person,
            selected: _selected?.ulid == person.ulid,
            onTap: () => setState(
              () => _selected = _selected?.ulid == person.ulid ? null : person,
            ),
          );
        },
      );

  Widget _confirmBar(ThemeData theme) => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            border: Border(top: BorderSide(color: theme.colorScheme.outlineVariant)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextField(
                controller: _statement,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'How can your family confirm this?',
                  hintText: 'e.g. My father is Hau Neng of Tedim',
                ),
              ),
              const SizedBox(height: 14),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: _submitting
                    ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2.4))
                    : Text('I am ${_selected!.displayName}'),
              ),
            ],
          ),
        ),
      );
}

class _AlreadyLinked extends StatelessWidget {
  const _AlreadyLinked({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.verified_user_outlined, size: 44, color: theme.colorScheme.primary),
            const SizedBox(height: 16),
            Text('You are recognised as $name.', textAlign: TextAlign.center, style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              'One account, one person. To change this, ask an administrator.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
          ],
        ),
      ),
    );
  }
}
