import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/story.dart';
import '../repositories/story_repository.dart';
import 'app_providers.dart';

final storyRepositoryProvider = Provider<StoryRepository>((ref) {
  return StoryRepository(ref.watch(apiClientProvider));
});

/// Stories about one person, as summaries.
///
/// Not cached offline, unlike the tree. A story is long-form text nobody has
/// asked to keep on the device, and pretending an empty list means "no
/// stories" when the phone simply has no copy would be the same lie the
/// timeline goes out of its way not to tell.
final personStoriesProvider = FutureProvider.family<List<Story>, String>((ref, personUlid) {
  return ref.watch(storyRepositoryProvider).forPerson(personUlid);
});

/// One story, with its body.
final storyProvider = FutureProvider.family<Story, String>((ref, ulid) {
  return ref.watch(storyRepositoryProvider).read(ulid);
});
