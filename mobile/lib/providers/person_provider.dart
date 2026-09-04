import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/family_bundle.dart';
import '../models/person_detail.dart';
import '../models/media_item.dart';
import '../models/person_event.dart';
import '../repositories/person_repository.dart';
import '../core/errors/api_exception.dart';
import 'app_providers.dart';
import 'tree_provider.dart';

final personRepositoryProvider = Provider<PersonRepository>((ref) {
  return PersonRepository(ref.watch(apiClientProvider));
});

/// The parts of a profile, fetched independently.
///
/// Separate providers rather than one combined fetch: the header should appear
/// as soon as the person does, instead of waiting on a timeline the viewer may
/// not even be permitted to see.
final personProvider = FutureProvider.family<PersonDetail, String>((ref, ulid) async {
  try {
    return await ref.watch(personRepositoryProvider).person(ulid);
  } on ApiException catch (error) {
    // Only an unreachable server falls back to the device. A 403 or 404 is the
    // server's answer and must stand — serving a cached copy of a record
    // somebody has since lost access to would be a leak with extra steps.
    if (!error.isOffline) rethrow;

    final cached = await ref.read(treeCacheProvider).person(ulid);

    if (cached == null) rethrow;

    return PersonDetail(summary: cached, fromCache: true);
  }
});

final familyProvider = FutureProvider.family<FamilyBundle, String>((ref, ulid) async {
  try {
    return await ref.watch(personRepositoryProvider).family(ulid);
  } on ApiException catch (error) {
    if (!error.isOffline) rethrow;

    final cached = await ref.read(treeCacheProvider).family(ulid);

    if (cached == null) rethrow;

    return cached;
  }
});

final timelineProvider = FutureProvider.family<Timeline, String>((ref, ulid) async {
  try {
    return await ref.watch(personRepositoryProvider).timeline(ulid);
  } on ApiException catch (error) {
    if (!error.isOffline) rethrow;

    // The chronicle is not cached. Saying so beats an empty list, which would
    // read as a life with nothing in it.
    return const Timeline.notOnDevice();
  }
});

/// Photographs for one person.
///
/// Not cached offline: a signed URL expires, so a cached album would become a
/// grid of broken images rather than a useful offline copy.
final personMediaProvider =
    FutureProvider.family<MediaAlbum, String>((ref, ulid) {
  return ref.watch(personRepositoryProvider).media(ulid);
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
