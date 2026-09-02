import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../providers/review_provider.dart';
import '../../../widgets/form_banner.dart';

/// "That is not what my grandmother said."
///
/// Disagreeing is a first-class action, not a complaint form. The competing
/// version is recorded alongside the existing one and both survive — a family
/// archive where the only way to disagree is to overwrite somebody loses the
/// argument and the evidence at the same time.
class RaiseDisputeScreen extends ConsumerStatefulWidget {
  const RaiseDisputeScreen({
    super.key,
    required this.personUlid,
    required this.personName,
  });

  final String personUlid;
  final String personName;

  @override
  ConsumerState<RaiseDisputeScreen> createState() => _RaiseDisputeScreenState();
}

class _RaiseDisputeScreenState extends ConsumerState<RaiseDisputeScreen> {
  static const _fields = <String, String>{
    'birth_year': 'Year of birth',
    'death_year': 'Year of death',
    'first_name': 'First name',
    'last_name': 'Last name',
    'native_name': 'Name in own script',
    'gender': 'Gender',
  };

  final _formKey = GlobalKey<FormState>();
  final _value = TextEditingController();
  final _rationale = TextEditingController();

  String _field = 'birth_year';
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _value.dispose();
    _rationale.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await ref.read(reviewRepositoryProvider).raiseDispute(
            personUlid: widget.personUlid,
            field: _field,
            claimedValue: _value.text.trim(),
            rationale: _rationale.text.trim().isEmpty ? null : _rationale.text.trim(),
          );

      ref.invalidate(disputesProvider(widget.personUlid));

      if (mounted) Navigator.of(context).pop(true);
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
      appBar: AppBar(title: Text('Disagree · ${widget.personName}')),
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
            Text(
              'Nothing is deleted. Your version is recorded next to the one '
              'already there, and somebody with authority in this family '
              'decides — or records that both stand.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 20),
            Text('What is wrong?', style: theme.textTheme.labelLarge),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final entry in _fields.entries)
                  ChoiceChip(
                    label: Text(entry.value),
                    selected: _field == entry.key,
                    onSelected: _saving ? null : (_) => setState(() => _field = entry.key),
                  ),
              ],
            ),
            const SizedBox(height: 20),
            TextFormField(
              controller: _value,
              decoration: const InputDecoration(
                labelText: 'What should it say?',
              ),
              validator: (value) =>
                  (value?.trim().isEmpty ?? true) ? 'Say what you believe is right' : null,
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _rationale,
              maxLines: 4,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                labelText: 'How do you know?',
                alignLabelWithHint: true,
                helperText: 'A register, a gravestone, who told you',
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
                  : const Text('Record my version'),
            ),
          ],
        ),
      ),
    );
  }
}
