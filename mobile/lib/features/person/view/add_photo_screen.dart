import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../providers/person_provider.dart';
import '../../../widgets/form_banner.dart';

/// Confirming a photograph before it is uploaded.
///
/// The picture is shown at full width first. A photograph attached to the
/// wrong relative is tedious to undo and worse to leave, and the cheapest
/// moment to notice is before it is sent.
class AddPhotoScreen extends ConsumerStatefulWidget {
  const AddPhotoScreen({
    super.key,
    required this.personUlid,
    required this.personName,
    required this.filePath,
  });

  final String personUlid;
  final String personName;
  final String filePath;

  @override
  ConsumerState<AddPhotoScreen> createState() => _AddPhotoScreenState();
}

class _AddPhotoScreenState extends ConsumerState<AddPhotoScreen> {
  final _caption = TextEditingController();

  /// Private unless somebody deliberately says otherwise. A public photograph
  /// is served from a permanent URL that cannot be recalled.
  bool _isPrivate = true;

  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _caption.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await ref.read(personRepositoryProvider).uploadPhoto(
            personUlid: widget.personUlid,
            filePath: widget.filePath,
            caption: _caption.text,
            isPrivate: _isPrivate,
          );

      ref.invalidate(personMediaProvider(widget.personUlid));

      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (error) {
      // Not queued for later: the picked file is a path into a temporary
      // directory the system is free to clear, so a queued upload could find
      // nothing there by the time it ran.
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
      appBar: AppBar(title: Text('Photograph of ${widget.personName}')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
        children: [
          if (_error != null) ...[
            FormBanner(message: _error!, tone: theme.colorScheme.error),
            const SizedBox(height: 16),
          ],
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Image.file(File(widget.filePath), fit: BoxFit.cover),
          ),
          const SizedBox(height: 20),
          TextField(
            controller: _caption,
            decoration: const InputDecoration(
              labelText: 'Caption',
              helperText: 'Who is in it, where, when. Optional.',
            ),
            textCapitalization: TextCapitalization.sentences,
            maxLength: 500,
          ),
          const SizedBox(height: 8),
          SwitchListTile(
            value: !_isPrivate,
            onChanged: (value) => setState(() => _isPrivate = !value),
            title: const Text('Anyone can see this'),
            subtitle: Text(
              _isPrivate
                  ? 'Only people who can see this person will see it.'
                  : 'This will be readable by anyone with the link, and that '
                      'link does not expire.',
              style: theme.textTheme.bodySmall,
            ),
            contentPadding: EdgeInsets.zero,
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _saving ? null : _submit,
            child: _saving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Add photograph'),
          ),
        ],
      ),
    );
  }
}
