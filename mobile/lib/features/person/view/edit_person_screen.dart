import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/change_request.dart';
import '../../../models/person_detail.dart';
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
  late final _firstName = TextEditingController(text: widget.detail.summary.displayName.split(' ').first);
  late final _lastName = TextEditingController();
  late final _nativeName = TextEditingController(text: widget.detail.summary.nativeName ?? '');
  late final _birth = TextEditingController(text: widget.detail.summary.birthDisplay ?? '');
  late final _death = TextEditingController(text: widget.detail.summary.deathDisplay ?? '');
  final _reason = TextEditingController();

  bool _saving = false;
  String? _error;

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
      final outcome = await ref.read(reviewRepositoryProvider).editPerson(
            ulid: widget.detail.ulid,
            reason: _reason.text.trim().isEmpty ? null : _reason.text.trim(),
            changes: {
              'first_name': _firstName.text.trim(),
              if (_lastName.text.trim().isNotEmpty) 'last_name': _lastName.text.trim(),
              if (_nativeName.text.trim().isNotEmpty) 'native_name': _nativeName.text.trim(),
              if (_birth.text.trim().isNotEmpty) 'birth': _birth.text.trim(),
              if (_death.text.trim().isNotEmpty) 'death': _death.text.trim(),
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
                message: 'This record has been checked, so your correction '
                    'will be sent for review rather than applied straight away.',
                tone: theme.colorScheme.primary,
                icon: Icons.how_to_reg,
              ),
            const SizedBox(height: 18),
            TextFormField(
              controller: _firstName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'First name'),
              validator: (value) =>
                  (value?.trim().isEmpty ?? true) ? 'A first name is needed' : null,
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
              decoration: const InputDecoration(labelText: 'Name in your own script'),
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
            const SizedBox(height: 20),
            TextFormField(
              controller: _reason,
              maxLines: 3,
              textCapitalization: TextCapitalization.sentences,
              decoration: InputDecoration(
                labelText: _willBeReviewed ? 'Why? (the reviewer will read this)' : 'Why?',
                alignLabelWithHint: true,
                // The reason is what makes history readable later. Without it a
                // correction is just a value that changed for no stated cause.
                helperText: 'Kept with the change, so the next person knows why',
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
                  : Text(_willBeReviewed ? 'Suggest this correction' : 'Save the correction'),
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
