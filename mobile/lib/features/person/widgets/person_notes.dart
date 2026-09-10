import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/note.dart';
import '../../../providers/person_provider.dart';

/// What members have written about a person, to the audiences they chose.
///
/// Records go quiet — somebody hides their name and there is then no way to
/// say who they were to the people entitled to know. This is where that is
/// said. Every note carries who may read it, on the note itself, because a
/// writer who has to remember what they chose will eventually be wrong.
class PersonNotes extends ConsumerWidget {
  const PersonNotes({super.key, required this.ulid});

  final String ulid;

  Future<void> _remove(BuildContext context, WidgetRef ref, Note note) async {
    final messenger = ScaffoldMessenger.of(context);

    try {
      await ref.read(personRepositoryProvider).deleteNote(note.ulid);
      ref.invalidate(personNotesProvider(ulid));
    } catch (error) {
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            error is ApiException ? error.message : 'Could not remove it.',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final notes = ref.watch(personNotesProvider(ulid)).value ?? const <Note>[];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 6),
        Row(
          children: [
            Text('Notes', style: theme.textTheme.labelLarge),
            const Spacer(),
            TextButton.icon(
              onPressed: () => _compose(context, ref),
              icon: const Icon(Icons.edit_note, size: 20),
              label: const Text('Add'),
            ),
          ],
        ),

        if (notes.isEmpty)
          Text(
            'Nothing written yet. A note is how somebody is placed when the '
            'record itself cannot say.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),

        for (final note in notes)
          Card(
            margin: const EdgeInsets.only(top: 10),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 6, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(note.body, style: theme.textTheme.bodyMedium),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Icon(
                        Icons.visibility_outlined,
                        size: 14,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          [
                            note.reach,
                            if (note.authorName != null) note.authorName!,
                          ].join(' · '),
                          style: theme.textTheme.labelMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ),
                      if (note.mine)
                        IconButton(
                          onPressed: () => _remove(context, ref, note),
                          icon: const Icon(Icons.delete_outline, size: 18),
                          tooltip: 'Remove',
                          visualDensity: VisualDensity.compact,
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),

        const SizedBox(height: 18),
      ],
    );
  }

  Future<void> _compose(BuildContext context, WidgetRef ref) async {
    final written = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: _Compose(ulid: ulid),
      ),
    );

    if (written ?? false) ref.invalidate(personNotesProvider(ulid));
  }
}

class _Compose extends ConsumerStatefulWidget {
  const _Compose({required this.ulid});

  final String ulid;

  @override
  ConsumerState<_Compose> createState() => _ComposeState();
}

class _ComposeState extends ConsumerState<_Compose> {
  final _body = TextEditingController();
  NoteAudience _audience = NoteAudience.clan;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_body.text.trim().isEmpty) {
      setState(() => _error = 'Write something first.');

      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await ref
          .read(personRepositoryProvider)
          .addNote(
            personUlid: widget.ulid,
            body: _body.text.trim(),
            audience: _audience.wire,
          );

      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      // Everything, not only ApiException: a note that silently fails to save
      // is a thing somebody believes they have written down.
      if (mounted) {
        setState(
          () => _error = error is ApiException
              ? error.message
              : 'Could not save it. $error',
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SafeArea(
      child: ConstrainedBox(
        // Never taller than the screen, so the sheet cannot push its own
        // button past the bottom of it.
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.9,
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Write a note', style: theme.textTheme.titleLarge),
              const SizedBox(height: 12),

              // The middle scrolls and the button does not. With the keyboard
              // up there was no room for both, and it was Save that went off
              // the bottom — a sheet you cannot finish.
              Flexible(
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      TextField(
                        controller: _body,
                        maxLines: 4,
                        maxLength: 2000,
                        autofocus: true,
                        // Tapping anywhere else puts the keyboard away. In a
                        // sheet there is nothing else that will.
                        onTapOutside: (_) => FocusScope.of(context).unfocus(),
                        decoration: const InputDecoration(
                          hintText: 'Who they are, and how you know',
                          border: OutlineInputBorder(),
                        ),
                      ),
                      Text(
                        'Who can read this?',
                        style: theme.textTheme.labelLarge,
                      ),
                      const SizedBox(height: 4),
                      RadioGroup<NoteAudience>(
                        groupValue: _audience,
                        onChanged: (value) {
                          if (value == null) return;

                          // Choosing the audience means the writing is done.
                          FocusScope.of(context).unfocus();
                          setState(() => _audience = value);
                        },
                        child: Column(
                          children: [
                            for (final audience in NoteAudience.values)
                              RadioListTile<NoteAudience>(
                                value: audience,
                                enabled: !_saving,
                                contentPadding: EdgeInsets.zero,
                                visualDensity: VisualDensity.compact,
                                title: Text(audience.label),
                                subtitle: Text(audience.describe),
                              ),
                          ],
                        ),
                      ),
                      if (_error != null) ...[
                        const SizedBox(height: 8),
                        Text(
                          _error!,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.error,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _saving ? null : _save,
                  child: _saving
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2.2),
                        )
                      : const Text('Save note'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
