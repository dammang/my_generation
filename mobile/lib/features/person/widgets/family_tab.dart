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
    required this.onLinkFamily,
    required this.onReorderChildren,
    required this.onDeletePerson,
  });

  final FamilyBundle bundle;
  final void Function(PersonSummary person) onOpenPerson;
  final void Function(String relation) onAddRelative;

  /// The whole sequence for one marriage, in its new order.
  final void Function(String unionUlid, List<String> personUlids)
  onReorderChildren;

  final void Function(PersonSummary person) onDeletePerson;

  /// Somebody who married in belongs to a family of their own, and the archive
  /// has no way to work out which. Asked for rather than guessed at.
  final VoidCallback onLinkFamily;

  @override
  Widget build(BuildContext context) {
    final unattached = bundle.unattachedChildren;

    return ListView(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 96),
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(6, 4, 6, 12),
          child: OutlinedButton.icon(
            onPressed: onLinkFamily,
            icon: const Icon(Icons.link),
            label: const Text('Link to another family'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(44),
            ),
          ),
        ),
        if (bundle.fromCache)
          Padding(
            padding: const EdgeInsets.fromLTRB(6, 8, 6, 0),
            child: Text(
              'Saved on this device. Marriages are not grouped offline, so '
              'children are listed together.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        _Section(
          title: 'Parents',
          people: bundle.parents,
          emptyLabel: 'No parents recorded',
          addLabel: 'Add a parent',
          onAdd: () => onAddRelative('parent'),
          onOpenPerson: onOpenPerson,
          fromCache: bundle.fromCache,
        ),
        _Section(
          title: 'Siblings',
          people: bundle.siblings,
          emptyLabel: 'No siblings recorded',
          addLabel: 'Add a sibling',
          onAdd: () => onAddRelative('sibling'),
          onOpenPerson: onOpenPerson,
          fromCache: bundle.fromCache,
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
            onReorderChildren: onReorderChildren,
            onDeletePerson: onDeletePerson,
          ),
        if (bundle.unions.isEmpty)
          _Section(
            title: 'Spouse',
            people: bundle.spouses,
            emptyLabel: 'No marriage recorded',
            addLabel: 'Add a spouse',
            onAdd: () => onAddRelative('spouse'),
            onOpenPerson: onOpenPerson,
            fromCache: bundle.fromCache,
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
            fromCache: bundle.fromCache,
          ),
        if (unattached.isEmpty && bundle.unions.isEmpty)
          _Section(
            title: 'Children',
            people: const [],
            emptyLabel: 'No children recorded',
            addLabel: 'Add a child',
            onAdd: () => onAddRelative('child'),
            onOpenPerson: onOpenPerson,
            fromCache: bundle.fromCache,
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
    required this.onReorderChildren,
    required this.onDeletePerson,
  });

  final FamilyUnion union;
  final String personUlid;
  final void Function(PersonSummary person) onOpenPerson;
  final VoidCallback onAddChild;
  final void Function(String unionUlid, List<String> personUlids)
  onReorderChildren;
  final void Function(PersonSummary person) onDeletePerson;

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
        for (final (index, child) in union.children.indexed)
          PersonTile(
            person: child,
            onTap: () => onOpenPerson(child),
            // Position in the list, not the stored birth order: only some
            // children have one, and a list that showed "1st, 3rd, 3rd" would
            // be reporting a gap in the data as a fact about the family.
            label: childLabel(index, child),
            trailing: _ChildMenu(
              canMoveUp: index > 0,
              canMoveDown: index < union.children.length - 1,
              onMove: (by) => onReorderChildren(
                union.ulid,
                _moved(union.children, index, by),
              ),
              onDelete: () => onDeletePerson(child),
            ),
          ),
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

/// "1st son", "2nd daughter", "3rd child".
///
/// Birth order is how most families here actually name their children, so it
/// belongs on the row rather than being left for somebody to count.
String childLabel(int index, PersonSummary child) {
  final place = _ordinal(index + 1);

  final word = switch (child.gender) {
    'male' => 'son',
    'female' => 'daughter',
    _ => 'child',
  };

  // How they joined the family is a fact about their place in it, and a family
  // that records an adoption means it to be visible.
  final kind = switch (child.relationshipType) {
    'adopted' => ' · adopted',
    'step' => ' · step',
    'foster' => ' · foster',
    _ => '',
  };

  return '$place $word$kind';
}

String _ordinal(int n) => switch (n % 100) {
  11 || 12 || 13 => '${n}th',
  _ => switch (n % 10) {
    1 => '${n}st',
    2 => '${n}nd',
    3 => '${n}rd',
    _ => '${n}th',
  },
};

/// The sequence after moving one child by [by] places.
List<String> _moved(List<PersonSummary> children, int index, int by) {
  final ulids = children.map((c) => c.ulid).toList();
  final target = (index + by).clamp(0, ulids.length - 1);

  ulids.insert(target, ulids.removeAt(index));

  return ulids;
}

/// What can be done to one child's place in the family.
class _ChildMenu extends StatelessWidget {
  const _ChildMenu({
    required this.canMoveUp,
    required this.canMoveDown,
    required this.onMove,
    required this.onDelete,
  });

  final bool canMoveUp;
  final bool canMoveDown;
  final void Function(int by) onMove;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) => PopupMenuButton<String>(
    tooltip: 'Change this child',
    icon: const Icon(Icons.more_vert),
    onSelected: (choice) => switch (choice) {
      'up' => onMove(-1),
      'down' => onMove(1),
      _ => onDelete(),
    },
    itemBuilder: (context) => [
      PopupMenuItem(
        value: 'up',
        enabled: canMoveUp,
        child: const ListTile(
          contentPadding: EdgeInsets.zero,
          dense: true,
          leading: Icon(Icons.arrow_upward),
          title: Text('Move up'),
        ),
      ),
      PopupMenuItem(
        value: 'down',
        enabled: canMoveDown,
        child: const ListTile(
          contentPadding: EdgeInsets.zero,
          dense: true,
          leading: Icon(Icons.arrow_downward),
          title: Text('Move down'),
        ),
      ),
      const PopupMenuDivider(),
      PopupMenuItem(
        value: 'delete',
        child: ListTile(
          contentPadding: EdgeInsets.zero,
          dense: true,
          leading: Icon(
            Icons.delete_outline,
            color: Theme.of(context).colorScheme.error,
          ),
          title: Text(
            'Remove from the archive',
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ),
      ),
    ],
  );
}

class _Section extends StatelessWidget {
  const _Section({
    required this.title,
    required this.people,
    required this.emptyLabel,
    required this.addLabel,
    required this.onAdd,
    required this.onOpenPerson,
    required this.fromCache,
    this.note,
  });

  final String title;
  final List<PersonSummary> people;
  final String emptyLabel;
  final String addLabel;
  final VoidCallback onAdd;
  final void Function(PersonSummary person) onOpenPerson;

  /// Offline an empty list means "not saved here", not "none exist". Saying
  /// "no siblings recorded" to somebody whose phone simply never cached them
  /// is a claim about their family, not about the device.
  final bool fromCache;
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
              fromCache ? 'Not saved on this device' : (note ?? emptyLabel),
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
