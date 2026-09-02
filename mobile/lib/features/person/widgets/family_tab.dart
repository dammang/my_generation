import 'package:flutter/material.dart';

import '../../../models/family_bundle.dart';
import '../../../models/person_summary.dart';
import '../../../widgets/person_tile.dart';

/// Who this person belongs to.
///
/// Children are grouped under the marriage they belong to rather than listed
/// flat. A man with three marriages has three sets of children, and flattening
/// them loses which mother each had — which is usually the thing a family is
/// trying to record.
class FamilyTab extends StatelessWidget {
  const FamilyTab({
    super.key,
    required this.bundle,
    required this.onOpenPerson,
    required this.onAddRelative,
  });

  final FamilyBundle bundle;
  final void Function(PersonSummary person) onOpenPerson;
  final void Function(String relation) onAddRelative;

  @override
  Widget build(BuildContext context) {
    final unattached = bundle.unattachedChildren;

    return ListView(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 96),
      children: [
        _Section(
          title: 'Parents',
          people: bundle.parents,
          emptyLabel: 'No parents recorded',
          addLabel: 'Add a parent',
          onAdd: () => onAddRelative('parent'),
          onOpenPerson: onOpenPerson,
        ),
        _Section(
          title: 'Siblings',
          people: bundle.siblings,
          emptyLabel: 'No siblings recorded',
          addLabel: 'Add a sibling',
          onAdd: () => onAddRelative('sibling'),
          onOpenPerson: onOpenPerson,
          // Siblings are derived from shared parents, so this is worth saying:
          // without parents on record there is nothing to derive them from.
          note: bundle.parents.isEmpty && bundle.siblings.isEmpty
              ? 'Siblings are worked out from shared parents. Add a parent and '
                    'they will appear here.'
              : null,
        ),
        for (final union in bundle.unions)
          _UnionSection(
            union: union,
            personUlid: bundle.person.ulid,
            onOpenPerson: onOpenPerson,
            onAddChild: () => onAddRelative('child'),
          ),
        if (bundle.unions.isEmpty)
          _Section(
            title: 'Spouse',
            people: bundle.spouses,
            emptyLabel: 'No marriage recorded',
            addLabel: 'Add a spouse',
            onAdd: () => onAddRelative('spouse'),
            onOpenPerson: onOpenPerson,
          ),
        if (unattached.isNotEmpty)
          _Section(
            title: bundle.unions.isEmpty
                ? 'Children'
                : 'Children (no marriage recorded)',
            people: unattached,
            emptyLabel: 'No children recorded',
            addLabel: 'Add a child',
            onAdd: () => onAddRelative('child'),
            onOpenPerson: onOpenPerson,
          ),
        if (unattached.isEmpty && bundle.unions.isEmpty)
          _Section(
            title: 'Children',
            people: const [],
            emptyLabel: 'No children recorded',
            addLabel: 'Add a child',
            onAdd: () => onAddRelative('child'),
            onOpenPerson: onOpenPerson,
          ),
      ],
    );
  }
}

class _UnionSection extends StatelessWidget {
  const _UnionSection({
    required this.union,
    required this.personUlid,
    required this.onOpenPerson,
    required this.onAddChild,
  });

  final FamilyUnion union;
  final String personUlid;
  final void Function(PersonSummary person) onOpenPerson;
  final VoidCallback onAddChild;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final spouse = union.partnerOther(personUlid);
    final hidden = union.childrenCount - union.children.length;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Heading(
          title: spouse == null ? 'Marriage' : 'With ${spouse.displayName}',
          subtitle: [
            union.describe(),
            if (union.place != null) union.place!,
          ].join(' · '),
        ),
        if (spouse != null)
          PersonTile(person: spouse, onTap: () => onOpenPerson(spouse)),
        for (final child in union.children)
          PersonTile(person: child, onTap: () => onOpenPerson(child)),
        if (hidden > 0)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 2, 14, 6),
            child: Text(
              // The count comes from the server, so it is the truth even when
              // the records behind it are not visible to this viewer.
              hidden == 1
                  ? '1 more child is not shown to you'
                  : '$hidden more children are not shown to you',
              style: theme.textTheme.labelMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                fontStyle: FontStyle.italic,
              ),
            ),
          ),
        Align(
          alignment: Alignment.centerLeft,
          child: Padding(
            padding: const EdgeInsets.only(left: 6, bottom: 8),
            child: TextButton.icon(
              onPressed: onAddChild,
              icon: const Icon(Icons.add, size: 18),
              label: const Text('Add a child'),
            ),
          ),
        ),
      ],
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({
    required this.title,
    required this.people,
    required this.emptyLabel,
    required this.addLabel,
    required this.onAdd,
    required this.onOpenPerson,
    this.note,
  });

  final String title;
  final List<PersonSummary> people;
  final String emptyLabel;
  final String addLabel;
  final VoidCallback onAdd;
  final void Function(PersonSummary person) onOpenPerson;
  final String? note;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Heading(title: title),
        if (people.isEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 4),
            child: Text(
              note ?? emptyLabel,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        for (final person in people)
          PersonTile(person: person, onTap: () => onOpenPerson(person)),
        Align(
          alignment: Alignment.centerLeft,
          child: Padding(
            padding: const EdgeInsets.only(left: 6, bottom: 8),
            child: TextButton.icon(
              onPressed: onAdd,
              icon: const Icon(Icons.add, size: 18),
              label: Text(addLabel),
            ),
          ),
        ),
      ],
    );
  }
}

class _Heading extends StatelessWidget {
  const _Heading({required this.title, this.subtitle});

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(6, 16, 6, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title.toUpperCase(),
            style: theme.textTheme.labelLarge?.copyWith(
              letterSpacing: 0.8,
              color: theme.colorScheme.primary,
            ),
          ),
          if (subtitle != null && subtitle!.isNotEmpty)
            Text(
              subtitle!,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
        ],
      ),
    );
  }
}
