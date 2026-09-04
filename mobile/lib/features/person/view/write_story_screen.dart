import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../providers/story_provider.dart';
import '../../../widgets/form_banner.dart';

/// Writing a story about somebody.
///
/// Who may read it is asked here rather than defaulted quietly. Most of what a
/// family writes down is about people who are still alive, and the difference
/// between "Family" and "Public" is the difference between a private
/// recollection and publishing it.
class WriteStoryScreen extends ConsumerStatefulWidget {
  const WriteStoryScreen({super.key, required this.personUlid, required this.personName});

  final String personUlid;
  final String personName;

  @override
  ConsumerState<WriteStoryScreen> createState() => _WriteStoryScreenState();
}

class _WriteStoryScreenState extends ConsumerState<WriteStoryScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _summary = TextEditingController();
  final _body = TextEditingController();

  /// Family, not public. The safe default for a story about living relatives
  /// is not the one that publishes it.
  String _visibility = 'family';

  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _title.dispose();
    _summary.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await ref
          .read(storyRepositoryProvider)
          .write(
            title: _title.text.trim(),
            body: _body.text.trim(),
            personUlid: widget.personUlid,
            summary: _summary.text.trim(),
            visibility: _visibility,
          );

      ref.invalidate(personStoriesProvider(widget.personUlid));

      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (error) {
      // Not queued for later, unlike an event: a story is long-form writing
      // somebody just spent real effort on, and silently parking it in a sync
      // queue is how that effort goes missing. Better to say it did not send
      // and leave the text on screen.
      if (mounted) {
        setState(() {
          _saving = false;
          _error = error.message;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text('Story about ${widget.personName}')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
          children: [
            if (_error != null) ...[
              FormBanner(message: _error!, tone: theme.colorScheme.error),
              const SizedBox(height: 16),
            ],
            TextFormField(
              controller: _title,
              decoration: const InputDecoration(labelText: 'Title'),
              textCapitalization: TextCapitalization.sentences,
              validator: (value) =>
                  (value?.trim().isEmpty ?? true) ? 'Give the story a title.' : null,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _summary,
              decoration: const InputDecoration(
                labelText: 'One-line summary',
                helperText: 'Shown in the list. Optional.',
              ),
              textCapitalization: TextCapitalization.sentences,
              maxLength: 500,
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _body,
              decoration: const InputDecoration(labelText: 'The story', alignLabelWithHint: true),
              textCapitalization: TextCapitalization.sentences,
              maxLines: 12,
              minLines: 6,
              validator: (value) =>
                  (value?.trim().isEmpty ?? true) ? 'Write something before saving.' : null,
            ),
            const SizedBox(height: 20),
            DropdownButtonFormField<String>(
              initialValue: _visibility,
              decoration: const InputDecoration(labelText: 'Who can read this'),
              items: const [
                DropdownMenuItem(value: 'private', child: Text('Only me')),
                DropdownMenuItem(value: 'family', child: Text('This family')),
                DropdownMenuItem(value: 'clan', child: Text('This clan')),
                DropdownMenuItem(value: 'tribe', child: Text('The whole tribe')),
                DropdownMenuItem(value: 'public', child: Text('Anyone')),
              ],
              onChanged: (value) => setState(() => _visibility = value ?? 'family'),
            ),
            const SizedBox(height: 28),
            FilledButton(
              onPressed: _saving ? null : _submit,
              child: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Save story'),
            ),
          ],
        ),
      ),
    );
  }
}
