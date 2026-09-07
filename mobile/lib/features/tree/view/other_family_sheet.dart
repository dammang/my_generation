import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../models/person_summary.dart';
import '../../../providers/person_provider.dart';
import '../../../widgets/person_tile.dart';

/// Who somebody is in the family they came from.
///
/// A wife on her husband's chart is a name beside him and nothing else. Once
/// she is linked to her own record she has parents, brothers and sisters — and
/// none of them belong on this chart, which is about his line. So they are
/// shown here instead, with the one thing worth offering: a way into the
/// family she came from.
class OtherFamilySheet extends ConsumerWidget {
  const OtherFamilySheet({
    super.key,
    required this.person,
    required this.onOpenTheirFamily,
  });

  final PersonSummary person;

  /// Re-centres the chart on them, which turns their family into the chart.
  final VoidCallback onOpenTheirFamily;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final family = ref.watch(familyProvider(person.ulid));

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(person.displayName, style: theme.textTheme.titleLarge),
            if (person.generationLabel != null)
              Text(
                person.generationLabel!,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            const SizedBox(height: 12),
            family.when(
              loading: () => const Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              ),
              error: (error, _) => Text(
                error is ApiException
                    ? error.message
                    : 'Could not read their family.',
                style: theme.textTheme.bodyMedium,
              ),
              data: (bundle) => Flexible(
                child: ListView(
                  shrinkWrap: true,
                  children: [
                    if (bundle.parents.isNotEmpty) ...[
                      _Heading('Parents', style: theme.textTheme.labelLarge),
                      for (final parent in bundle.parents)
                        PersonTile(person: parent),
                    ],
                    if (bundle.siblings.isNotEmpty) ...[
                      _Heading(
                        'Brothers and sisters',
                        style: theme.textTheme.labelLarge,
                      ),
                      for (final sibling in bundle.siblings)
                        PersonTile(person: sibling),
                    ],
                    if (bundle.parents.isEmpty && bundle.siblings.isEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        child: Text(
                          'Nobody from their own family is recorded yet.',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: () {
                Navigator.of(context).pop();
                onOpenTheirFamily();
              },
              icon: const Icon(Icons.account_tree_outlined),
              label: Text('View ${person.displayName}\'s family'),
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Heading extends StatelessWidget {
  const _Heading(this.text, {this.style});

  final String text;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(0, 8, 0, 4),
    child: Text(text, style: style),
  );
}
