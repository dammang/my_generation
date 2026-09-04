import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
import '../models/story.dart';

/// Family narratives.
///
/// Separate from PersonRepository because a story is not only ever about one
/// person — the same endpoint answers "stories in this tribe" — and folding it
/// into the person repository would make that read like an accident.
class StoryRepository {
  const StoryRepository(this._api);

  final ApiClient _api;

  /// Summaries only, as the server sends them.
  Future<List<Story>> forPerson(String personUlid) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.stories,
      query: {'person_ulid': personUlid},
      parse: (data) => (data as List?) ?? const [],
    );

    return (envelope.data ?? const [])
        .whereType<Map>()
        .map((e) => Story.fromJson(e.cast<String, dynamic>()))
        .toList(growable: false);
  }

  /// One story, with its body.
  Future<Story> read(String ulid) async {
    final envelope = await _api.get<Map<String, dynamic>>(
      ApiPaths.story(ulid),
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return Story.fromJson(envelope.data!);
  }

  Future<Story> write({
    required String title,
    required String body,
    String? personUlid,
    String? summary,
    String? visibility,
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.stories,
      body: {
        'title': title,
        'body': body,
        'person_ulid': ?personUlid,
        if (summary != null && summary.trim().isNotEmpty) 'summary': summary,
        'visibility': ?visibility,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return Story.fromJson(envelope.data!);
  }
}
