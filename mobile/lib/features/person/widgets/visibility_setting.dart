import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../models/person_detail.dart';
import '../../../models/visibility_choice.dart';
import '../../../providers/person_provider.dart';

/// Who may see me.
///
/// Shown only on your own claimed record. It takes effect the moment it is
/// tapped rather than becoming a proposal somebody reviews: nobody should have
/// to wait in a queue to stop being visible.
class VisibilitySetting extends ConsumerStatefulWidget {
  const VisibilitySetting({super.key, required this.detail});

  final PersonDetail detail;

  @override
  ConsumerState<VisibilitySetting> createState() => _VisibilitySettingState();
}

class _VisibilitySettingState extends ConsumerState<VisibilitySetting> {
  VisibilityChoice? _saving;

  Future<void> _choose(VisibilityChoice choice) async {
    if (_saving != null || choice == widget.detail.visibility) return;

    setState(() => _saving = choice);

    final messenger = ScaffoldMessenger.of(context);

    try {
      await ref
          .read(personRepositoryProvider)
          .setVisibility(widget.detail.ulid, choice.wire);

      ref.invalidate(personProvider(widget.detail.ulid));

      messenger.showSnackBar(
        SnackBar(content: Text('Saved · ${choice.label}')),
      );
    } catch (error) {
      // Said plainly, because the screen still shows the old choice and
      // somebody who believes a privacy setting saved when it did not is
      // worse off than somebody who knows it failed.
      messenger.showSnackBar(SnackBar(content: Text('Not saved. $error')));
    } finally {
      if (mounted) setState(() => _saving = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final current = widget.detail.visibility;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Who can see me', style: theme.textTheme.labelLarge),
        const SizedBox(height: 4),
        Text(
          'Your place in the tree always shows. This is about your name, '
          'dates and photographs.',
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 8),
        RadioGroup<VisibilityChoice>(
          groupValue: current,
          onChanged: (choice) {
            if (choice != null && _saving == null) _choose(choice);
          },
          child: Column(
            children: [
              for (final choice in VisibilityChoice.values)
                RadioListTile<VisibilityChoice>(
                  value: choice,
                  enabled: _saving == null,
                  contentPadding: EdgeInsets.zero,
                  title: Text(choice.label),
                  subtitle: Text(choice.describe),
                  secondary: _saving == choice
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : null,
                ),
            ],
          ),
        ),
        if (widget.detail.summary.isLiving == false) ...[
          const SizedBox(height: 4),
          Text(
            'A recorded death lifts this, so the family can still be read '
            'generations from now.',
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
        const SizedBox(height: 18),
      ],
    );
  }
}
