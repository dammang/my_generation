import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/tribe_summary.dart';
import '../../../providers/clan_provider.dart';
import '../../../providers/onboarding_provider.dart';
import '../../../widgets/form_banner.dart';

/// Asking to start a clan.
///
/// A request, never an act: a clan is a claim about how a family is organised,
/// and one person deciding that alone is how two versions of the same family
/// end up in one archive. Whoever runs the tribe decides.
class StartClanScreen extends ConsumerStatefulWidget {
  const StartClanScreen({super.key});

  @override
  ConsumerState<StartClanScreen> createState() => _StartClanScreenState();
}

class _StartClanScreenState extends ConsumerState<StartClanScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _nativeName = TextEditingController();
  final _statement = TextEditingController();

  String? _tribeUlid;
  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _nativeName.dispose();
    _statement.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final tribe = _tribeUlid;
    if (tribe == null) {
      setState(() => _error = 'Choose which tribe this clan belongs to.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await ref
          .read(clanRepositoryProvider)
          .requestClan(
            tribeUlid: tribe,
            name: _name.text.trim(),
            nativeName: _nativeName.text.trim(),
            statement: _statement.text.trim(),
          );

      ref.invalidate(clanRegistrationsProvider);

      if (mounted) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Asked to start ${_name.text.trim()}. '
              'Whoever runs the tribe will decide.',
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
    final tribes = ref.watch(tribesProvider(''));

    return Scaffold(
      appBar: AppBar(title: const Text('Start a clan')),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
            children: [
              Text('What happens next', style: theme.textTheme.titleMedium),
              const SizedBox(height: 6),
              Text(
                'Nothing is created yet. Whoever runs the tribe reads this and '
                'decides. If they approve, the clan is created and you become '
                'its administrator — you can then appoint the rest of the '
                'committee yourself.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 24),
              if (_error != null) ...[
                FormBanner(
                  message: _error!,
                  tone: theme.colorScheme.error,
                  icon: Icons.error_outline,
                ),
                const SizedBox(height: 16),
              ],
              tribes.when(
                loading: () => const LinearProgressIndicator(),
                error: (_, _) => FormBanner(
                  message: 'Could not read the list of tribes.',
                  tone: theme.colorScheme.error,
                  icon: Icons.cloud_off_outlined,
                ),
                data: (all) => _TribeField(
                  tribes: all,
                  value: _tribeUlid,
                  // One tribe is the ordinary case, and asking somebody to
                  // choose from a list of one is a question with one answer.
                  onChanged: (ulid) => setState(() => _tribeUlid = ulid),
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _name,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(
                  labelText: 'Clan name',
                  helperText: 'The name your family is known by.',
                ),
                validator: (value) => (value ?? '').trim().length < 2
                    ? 'Enter the name of the clan.'
                    : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _nativeName,
                decoration: const InputDecoration(
                  labelText: 'Name in your own language (optional)',
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _statement,
                maxLines: 4,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'Why this clan',
                  alignLabelWithHint: true,
                  helperText:
                      'Who it descends from, and how you know. This is what '
                      'the decision is made on.',
                ),
              ),
              const SizedBox(height: 28),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(52),
                ),
                child: _submitting
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Send the request'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Which tribe the clan sits in.
///
/// Preselects when there is only one, which is what almost every account sees.
class _TribeField extends StatelessWidget {
  const _TribeField({
    required this.tribes,
    required this.value,
    required this.onChanged,
  });

  final List<TribeSummary> tribes;
  final String? value;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    if (tribes.isEmpty) {
      return FormBanner(
        message:
            'You do not belong to a tribe yet, so there is nothing to start a '
            'clan inside. Join one first.',
        tone: Theme.of(context).colorScheme.tertiary,
        icon: Icons.groups_outlined,
      );
    }

    if (tribes.length == 1 && value != tribes.first.ulid) {
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => onChanged(tribes.first.ulid),
      );
    }

    return DropdownButtonFormField<String>(
      initialValue: value,
      isExpanded: true,
      decoration: const InputDecoration(labelText: 'Tribe'),
      items: [
        for (final tribe in tribes)
          DropdownMenuItem(value: tribe.ulid, child: Text(tribe.name)),
      ],
      onChanged: onChanged,
    );
  }
}
