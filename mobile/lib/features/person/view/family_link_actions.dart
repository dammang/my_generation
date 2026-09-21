import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../providers/person_provider.dart';
import '../../../providers/review_provider.dart';
import 'link_family_sheet.dart';

/// Changing or taking back "Link to another family".
///
/// A link is easy to make by mistake — the list opens full and one tap files
/// it — so both the Family tab and the Edits screen offer the way back. Both
/// are proposals, like the link itself: saying somebody is *not* kin to a
/// family is as much a claim about that family as saying they are.

/// Opens the link sheet again. When [replacing] names a proposal still
/// waiting, it is withdrawn once the new one is sent — and only then, so
/// dismissing the sheet loses nothing.
Future<bool> changeFamilyLink(
  BuildContext context,
  WidgetRef ref, {
  required String personUlid,
  required String personName,
  String? replacing,
}) async {
  final sent = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (_) =>
        LinkFamilySheet(personUlid: personUlid, personName: personName),
  );

  if (sent != true) return false;

  if (replacing != null) {
    try {
      await ref.read(reviewRepositoryProvider).withdraw(replacing);
    } on ApiException {
      // Already decided in the meantime: the new proposal still stands, and
      // the reviewer sees both.
    }
  }

  _refresh(ref, personUlid);

  return true;
}

/// Proposes that somebody is not in the family they were linked to.
Future<bool> unlinkFamily(
  BuildContext context,
  WidgetRef ref, {
  required String personUlid,
  required String personName,
  String? familyName,
}) async {
  final family = familyName == null ? 'that family' : 'the $familyName family';

  final confirmed = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: const Text('Unlink from this family?'),
      content: Text(
        '$personName will no longer be counted as coming from $family. '
        'Somebody who administers it will confirm.',
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(true),
          child: const Text('Unlink'),
        ),
      ],
    ),
  );

  if (confirmed != true || !context.mounted) return false;

  final messenger = ScaffoldMessenger.of(context);

  try {
    await ref
        .read(reviewRepositoryProvider)
        .editPerson(
          ulid: personUlid,
          changes: const {'family_branch_ulid': null},
          reason: '$personName was linked to $family by mistake.',
        );

    _refresh(ref, personUlid);

    messenger.showSnackBar(
      SnackBar(
        content: Text('Sent for review: unlinking $personName from $family.'),
      ),
    );

    return true;
  } on ApiException catch (error) {
    messenger.showSnackBar(SnackBar(content: Text(error.message)));
    return false;
  }
}

void _refresh(WidgetRef ref, String personUlid) {
  ref.invalidate(reviewQueueProvider('mine'));
  ref.invalidate(reviewQueueProvider('review'));
  invalidatePerson(ref, personUlid);
}
