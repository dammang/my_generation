import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/family_bundle.dart';
import '../models/person_detail.dart';
import '../models/person_event.dart';
import '../repositories/person_repository.dart';
import 'app_providers.dart';
import 'tree_provider.dart';

final personRepositoryProvider = Provider<PersonRepository>((ref) {
  return PersonRepository(ref.watch(apiClientProvider));
});

/// The three halves of a profile, fetched independently.
///
/// Separate providers rather than one combined fetch: the header should appear
/// as soon as the person does, instead of waiting on a timeline the viewer may
/// not even be permitted to see.
final personProvider = FutureProvider.family<PersonDetail, String>((ref, ulid) {
  return ref.watch(personRepositoryProvider).person(ulid);
});

final familyProvider = FutureProvider.family<FamilyBundle, String>((ref, ulid) {
  return ref.watch(personRepositoryProvider).family(ulid);
});

final timelineProvider = FutureProvider.family<Timeline, String>((ref, ulid) {
  return ref.watch(personRepositoryProvider).timeline(ulid);
});

final eventTypesProvider = FutureProvider<List<EventTypeOption>>((ref) {
  return ref.watch(personRepositoryProvider).eventTypes();
});

/// Everything that must be re-read after the graph around [ulid] changes.
///
/// Adding a grandfather changes the profile, the family lists and the tree, and
/// a screen still showing the old family after a successful write reads as the
/// write having failed. Invalidation is centralised here so no caller has to
/// remember the full list.
void invalidatePerson(WidgetRef ref, String ulid, {String? alsoUlid}) {
  ref.invalidate(personProvider(ulid));
  ref.invalidate(familyProvider(ulid));
  ref.invalidate(timelineProvider(ulid));

  if (alsoUlid != null) {
    ref.invalidate(personProvider(alsoUlid));
    ref.invalidate(familyProvider(alsoUlid));
  }

  ref.invalidate(treeProvider);
}
