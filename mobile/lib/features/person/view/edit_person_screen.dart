import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/change_request.dart';
import '../../../models/person_detail.dart';
import '../../../providers/clan_provider.dart';
import '../../../providers/person_provider.dart';
import '../../../providers/review_provider.dart';
import '../../../widgets/form_banner.dart';

/// Correcting a record.
///
/// The same form does two different things depending on the record and the
/// account: the change lands, or it becomes a suggestion for somebody with
/// authority to accept. The screen says which is going to happen *before* the
/// button is pressed, because "Save" that quietly means "ask" is a lie, and
/// the contributor stops watching for the answer.
class EditPersonScreen extends ConsumerStatefulWidget {
  const EditPersonScreen({super.key, required this.detail});

  final PersonDetail detail;

  @override
  ConsumerState<EditPersonScreen> createState() => _EditPersonScreenState();
}

class _EditPersonScreenState extends ConsumerState<EditPersonScreen> {
  final _formKey = GlobalKey<FormState>();
  late final _firstName = TextEditingController(
    text: widget.detail.summary.displayName.split(' ').first,
  );
  late final _lastName = TextEditingController();
  late final _nativeName = TextEditingController(
    text: widget.detail.summary.nativeName ?? '',
  );
  late final _birth = TextEditingController(
    text: widget.detail.summary.birthDisplay ?? '',
  );
  late final _death = TextEditingController(
    text: widget.detail.summary.deathDisplay ?? '',
  );
  final _reason = TextEditingController();

  bool _saving = false;
  String? _error;

  /// Null means "leave it alone"; the sentinel below means "clear it".
  ///
  /// Two different intentions that both look like an empty dropdown, and
  /// sending the wrong one either wipes a label nobody touched or quietly
  /// keeps one somebody removed.
  String? _generationUlid;
  bool _generationTouched = false;

  static const String _noGeneration = 'none';

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _nativeName.dispose();
    _birth.dispose();
    _death.dispose();
    _reason.dispose();
    super.dispose();
  }

  /// A verified record cannot be edited outright by everyone. The server has
  /// the final say; this is only what to promise on the button.
  bool get _willBeReviewed => widget.detail.summary.isVerified;

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final outcome = await ref
          .read(reviewRepositoryProvider)
          .editPerson(
            ulid: widget.detail.ulid,
            reason: _reason.text.trim().isEmpty ? null : _reason.text.trim(),
            changes: {
              'first_name': _firstName.text.trim(),
              if (_lastName.text.trim().isNotEmpty)
                'last_name': _lastName.text.trim(),
              if (_nativeName.text.trim().isNotEmpty)
                'native_name': _nativeName.text.trim(),
              if (_birth.text.trim().isNotEmpty) 'birth': _birth.text.trim(),
              if (_death.text.trim().isNotEmpty) 'death': _death.text.trim(),
              if (_generationTouched)
                'generation_ulid': _generationUlid == _noGeneration
                    ? null
                    : _generationUlid,
            },
          );

      invalidatePerson(ref, widget.detail.ulid);
      ref.invalidate(reviewQueueProvider('mine'));

      if (mounted) Navigator.of(context).pop(outcome);
    } on ApiException catch (error) {
      setState(() {
        _saving = false;
        _error = error.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text('Correct ${widget.detail.displayName}')),
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
            if (_willBeReviewed)
              FormBanner(
                message:
                    'This record has been checked, so your correction '
                    'will be sent for review rather than applied straight away.',
                tone: theme.colorScheme.primary,
                icon: Icons.how_to_reg,
              ),
            const SizedBox(height: 18),
            TextFormField(
              controller: _firstName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'First name'),
              validator: (value) => (value?.trim().isEmpty ?? true)
                  ? 'A first name is needed'
                  : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _lastName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Last name'),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _nativeName,
              decoration: const InputDecoration(
                labelText: 'Name in your own script',
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _birth,
              decoration: const InputDecoration(
                labelText: 'Born',
                helperText: 'e.g. 1902, abt. 1902, before 1910',
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _death,
              decoration: const InputDecoration(labelText: 'Died'),
            ),
            const SizedBox(height: 14),
            _GenerationField(
              tribeUlid: widget.detail.tribeUlid,
              currentLabel: widget.detail.summary.generationLabel,
              value: _generationUlid,
              onChanged: (ulid) => setState(() {
                _generationUlid = ulid;
                _generationTouched = true;
              }),
              noneValue: _noGeneration,
            ),
            const SizedBox(height: 20),
            TextFormField(
              controller: _reason,
              maxLines: 3,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(
                labelText: _willBeReviewed
                    ? 'Why? (the reviewer will read this)'
                    : 'Why?',
                alignLabelWithHint: true,
                // The reason is what makes history readable later. Without it a
                // correction is just a value that changed for no stated cause.
                helperText:
                    'Kept with the change, so the next person knows why',
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
                  : Text(
                      _willBeReviewed
                          ? 'Suggest this correction'
                          : 'Save the correction',
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Says what actually happened, which is not always what was asked for.
void showEditOutcome(BuildContext context, EditOutcome outcome) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(
        outcome.applied
            ? 'The correction was saved.'
            : 'Sent for review. You can follow it under Contributions.',
      ),
      duration: const Duration(seconds: 4),
    ),
  );
}

/// Which generation this person is counted at.
///
/// Almost nobody needs it: the number is worked out from the family branch's
/// founder. It is here for the person that cannot be worked out — somebody who
/// married in stands at their partner's generation, not at their own distance
/// from a founder this archive may not even hold.
class _GenerationField extends ConsumerWidget {
  const _GenerationField({
    required this.tribeUlid,
    required this.currentLabel,
    required this.value,
    required this.onChanged,
    required this.noneValue,
  });

  final String? tribeUlid;
  final String? currentLabel;
  final String? value;
  final ValueChanged<String?> onChanged;
  final String noneValue;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);

    // Nothing to choose from, and nothing to explain: a person outside any
    // tribe has no set of labels to belong to.
    if (tribeUlid == null) return const SizedBox.shrink();

    final generations = ref.watch(generationsProvider(tribeUlid!));

    return generations.when(
      loading: () => const SizedBox.shrink(),
      error: (_, _) => const SizedBox.shrink(),
      data: (all) {
        if (all.isEmpty) {
          return Text(
            'No generation labels exist yet. Whoever runs the clan can add '
            'them on its page.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          );
        }

        return DropdownButtonFormField<String>(
          initialValue: value,
          isExpanded: true,
          decoration: InputDecoration(
            labelText: 'Generation',
            helperText: currentLabel == null
                ? 'Normally counted automatically. Set it only to override.'
                : 'Now showing as $currentLabel. Set it only to override.',
          ),
          items: [
            DropdownMenuItem(
              value: noneValue,
              child: const Text('Counted automatically'),
            ),
            for (final generation in all)
              DropdownMenuItem(
                value: generation.ulid,
                child: Text(generation.label),
              ),
          ],
          onChanged: onChanged,
        );
      },
    );
  }
}
