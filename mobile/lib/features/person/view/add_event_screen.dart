import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../providers/person_provider.dart';
import '../../../providers/sync_provider.dart';
import '../../../widgets/form_banner.dart';

/// Recording something that happened.
///
/// The date field takes whatever the source says. Most of what a family
/// remembers is approximate, and a date picker would force every "sometime in
/// the fifties" to become a specific day nobody actually knows.
class AddEventScreen extends ConsumerStatefulWidget {
  const AddEventScreen({
    super.key,
    required this.personUlid,
    required this.personName,
  });

  final String personUlid;
  final String personName;

  @override
  ConsumerState<AddEventScreen> createState() => _AddEventScreenState();
}

class _AddEventScreenState extends ConsumerState<AddEventScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _date = TextEditingController();

  String? _type;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _date.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_type == null) {
      setState(() => _error = 'Choose what kind of event this was.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final result = await ref
          .read(personRepositoryProvider)
          .addEvent(
            personUlid: widget.personUlid,
            eventType: _type!,
            title: _title.text.trim().isEmpty ? null : _title.text.trim(),
            description: _description.text.trim().isEmpty
                ? null
                : _description.text.trim(),
            date: _date.text.trim().isEmpty ? null : _date.text.trim(),
          );

      // The person's own death date and their chronicle are separate records,
      // so the server flags it when they end up disagreeing.
      invalidatePerson(ref, widget.personUlid);

      if (!mounted) return;

      if (result.warnings.isNotEmpty) {
        await showDialog<void>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Recorded, with something to check'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final warning in result.warnings)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text(warning.message),
                  ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Got it'),
              ),
            ],
          ),
        );
      }

      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (error) {
      if (error.isOffline) {
        await ref.read(syncControllerProvider.notifier).enqueue(
              kind: 'add_event',
              subjectUlid: widget.personUlid,
              subjectLabel: widget.personName,
              payload: {
                'person_ulid': widget.personUlid,
                'event_type': _type,
                'title': _title.text.trim().isEmpty ? null : _title.text.trim(),
                'description':
                    _description.text.trim().isEmpty ? null : _description.text.trim(),
                'date': _date.text.trim().isEmpty ? null : _date.text.trim(),
              },
            );

        if (!mounted) return;

        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Saved on this device. It will be sent when you are back online.'),
          ),
        );

        Navigator.of(context).pop(true);

        return;
      }

      setState(() {
        _saving = false;
        _error = error.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final types = ref.watch(eventTypesProvider);

    return Scaffold(
      appBar: AppBar(title: Text('Add an event · ${widget.personName}')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 40),
          children: [
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: FormBanner(
                  message: _error!,
                  tone: theme.colorScheme.error,
                  icon: Icons.error_outline,
                ),
              ),
            Text('What happened?', style: theme.textTheme.labelLarge),
            const SizedBox(height: 8),
            types.when(
              loading: () => const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: LinearProgressIndicator(),
              ),
              error: (error, _) => Text(
                'Could not load event types.',
                style: theme.textTheme.bodyMedium,
              ),
              // The vocabulary comes from the server so a tribe can extend it;
              // hard-coding the list here would make that setting a lie.
              data: (options) => Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final option in options)
                    ChoiceChip(
                      label: Text(option.label),
                      selected: _type == option.slug,
                      onSelected: _saving
                          ? null
                          : (_) => setState(() => _type = option.slug),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            TextFormField(
              controller: _date,
              decoration: const InputDecoration(
                labelText: 'When (optional)',
                helperText: 'e.g. 1948, abt. 1948, before 1950, March 1948',
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _title,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'Title (optional)',
                helperText: 'Left blank, the event type is used',
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _description,
              textCapitalization: TextCapitalization.sentences,
              maxLines: 5,
              decoration: const InputDecoration(
                labelText: 'What do you remember? (optional)',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 28),
            FilledButton(
              onPressed: _saving ? null : _submit,
              child: _saving
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Record it'),
            ),
          ],
        ),
      ),
    );
  }
}
