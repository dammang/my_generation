import 'package:dio/dio.dart';

import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
import '../core/network/api_envelope.dart';
import '../models/family_bundle.dart';
import '../models/media_item.dart';
import '../models/person_detail.dart';
import '../models/person_event.dart';
import '../models/family_branch_summary.dart';
import '../models/person_summary.dart';

/// The outcome of adding a relative.
///
/// Warnings ride along with success on purpose: "born 20 years after the
/// father's recorded death" is worth saying and not worth refusing. A caller
/// that ignores [warnings] silently discards the server's doubt.
class AddRelativeResult {
  const AddRelativeResult({
    required this.person,
    required this.created,
    this.warnings = const [],
    this.queued = false,
  });

  /// Recorded on the device, waiting for a connection. Reported honestly:
  /// saying "added" for something the server has never seen is how somebody
  /// discovers a week later that it never arrived.
  const AddRelativeResult.queued()
    : person = null,
      created = true,
      warnings = const [],
      queued = true;

  final PersonSummary? person;

  /// False when the server matched an existing person instead of creating one.
  final bool created;
  final List<ApiWarning> warnings;
  final bool queued;
}

class PersonRepository {
  PersonRepository(this._api);

  final ApiClient _api;

  /// Named family lines, for linking somebody who married in.
  ///
  /// A spouse belongs to a family of their own, and the archive has no way to
  /// know which one — so it is asked for rather than guessed at.
  Future<List<FamilyBranchSummary>> familyBranches({String? query}) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.familyBranches,
      query: {
        if (query != null && query.trim().isNotEmpty) 'q': query.trim(),
        'per_page': 30,
      },
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map(
          (b) =>
              FamilyBranchSummary.fromJson((b as Map).cast<String, dynamic>()),
        )
        .toList(growable: false);
  }

  /// People whose name begins with [query].
  ///
  /// The server matches a PREFIX, not a substring — display_name for a given
  /// name, sort_name ("surname firstname", folded to ASCII) for a family name.
  /// So "cole" finds James Cole and "james" finds him too, but "ole" finds
  /// nobody. That is the index doing its job rather than a bug, and the screen
  /// says so rather than leaving somebody to conclude the person is missing.
  ///
  /// Results are already masked and scoped by the server: this cannot be used
  /// to discover who exists in a family the searcher has no part in.
  Future<List<PersonSummary>> search(String query, {int perPage = 25}) async {
    final trimmed = query.trim();

    if (trimmed.length < 2) return const [];

    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.people,
      query: {'q': trimmed, 'per_page': perPage},
      parse: (data) => data as List<dynamic>,
    );

    return (envelope.data ?? const [])
        .map((p) => PersonSummary.fromJson((p as Map).cast<String, dynamic>()))
        .toList(growable: false);
  }

  /// The order children are listed in, sent whole.
  ///
  /// The entire sequence rather than one move: swapping two children is two
  /// writes, and a client that sent them separately could leave two siblings
  /// sharing a place if the second call failed.
  Future<void> orderChildren({
    required String unionUlid,
    required List<String> personUlids,
  }) => _api.patch<Map<String, dynamic>>(
    ApiPaths.unionChildOrder(unionUlid),
    body: {'person_ulids': personUlids},
    parse: (data) => (data as Map).cast<String, dynamic>(),
  );

  /// Moves a child from one of a person's marriages to another.
  ///
  /// One call, because the server does both halves in a transaction: detaching
  /// and re-attaching separately would leave a child with no parents at all if
  /// the second request never arrived, and on a phone that is not a
  /// hypothetical.
  Future<void> moveChildToUnion({
    required String fromUnionUlid,
    required String toUnionUlid,
    required String personUlid,
  }) => _api.post<Map<String, dynamic>>(
    ApiPaths.unionChildMove(fromUnionUlid, personUlid),
    body: {'union_ulid': toUnionUlid},
    parse: (data) => (data as Map).cast<String, dynamic>(),
  );

  /// Removes a record from the archive.
  ///
  /// Who may see this person.
  ///
  /// Its own endpoint on the server, so it applies at once rather than
  /// becoming a change request somebody has to approve. Nobody should have to
  /// wait for a reviewer to stop being visible.
  Future<void> setVisibility(String ulid, String level) =>
      _api.patch<Map<String, dynamic>>(
        ApiPaths.personVisibility(ulid),
        body: {'privacy_level': level},
        parse: (data) => (data as Map).cast<String, dynamic>(),
      );

  /// A soft delete on the server: the person leaves the graph, the history of
  /// what was recorded about them does not.
  Future<void> deletePerson(String ulid) => _api.delete(ApiPaths.person(ulid));

  Future<PersonDetail> person(String ulid) async {
    final envelope = await _api.get<Map<String, dynamic>>(
      ApiPaths.person(ulid),
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return PersonDetail.fromJson(envelope.data!);
  }

  Future<FamilyBundle> family(String ulid) async {
    final envelope = await _api.get<Map<String, dynamic>>(
      ApiPaths.personFamily(ulid),
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return FamilyBundle.fromJson(envelope.data!);
  }

  Future<Timeline> timeline(String ulid) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.personTimeline(ulid),
      parse: (data) => (data as List?) ?? const [],
    );

    return Timeline(
      events: (envelope.data ?? const [])
          .whereType<Map>()
          .map((e) => PersonEvent.fromJson(e.cast<String, dynamic>()))
          .toList(growable: false),
      // The server says so explicitly rather than the client inferring it from
      // an empty list, which would be the wrong inference half the time.
      withheld: envelope.meta['withheld'] as bool? ?? false,
    );
  }

  /// Photographs attached to a person.
  ///
  /// Withheld is reported by the server rather than inferred from an empty
  /// list, for the same reason the timeline does it: the two mean different
  /// things and must not render the same.
  Future<MediaAlbum> media(String ulid) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.personMedia(ulid),
      parse: (data) => (data as List?) ?? const [],
    );

    return MediaAlbum(
      items: (envelope.data ?? const [])
          .whereType<Map>()
          .map((e) => MediaItem.fromJson(e.cast<String, dynamic>()))
          .toList(growable: false),
      withheld: envelope.meta['withheld'] as bool? ?? false,
    );
  }

  /// Uploads a photograph and attaches it to a person.
  ///
  /// Multipart rather than a base64 field: a photograph from a modern phone is
  /// several megabytes, and base64 would add a third to that on somebody's
  /// mobile data for no gain.
  Future<MediaItem> uploadPhoto({
    required String personUlid,
    required String filePath,
    String? caption,
    bool isPrivate = true,
  }) async {
    final form = FormData.fromMap({
      'person_ulid': personUlid,
      'file': await MultipartFile.fromFile(filePath),
      if (caption != null && caption.trim().isNotEmpty)
        'caption': caption.trim(),
      // Sent as 0/1: a Dart bool becomes the string "true", which PHP's
      // boolean validation rejects.
      'is_private': isPrivate ? 1 : 0,
    });

    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.media,
      body: form,
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return MediaItem.fromJson(envelope.data!);
  }

  Future<List<EventTypeOption>> eventTypes() async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.eventTypes,
      parse: (data) => (data as List?) ?? const [],
    );

    return (envelope.data ?? const [])
        .whereType<Map>()
        .map((e) => EventTypeOption.fromJson(e.cast<String, dynamic>()))
        .toList(growable: false);
  }

  /// A recorded event, and any doubt the server attached to it.
  ///
  /// Recording a death for somebody still marked living succeeds and warns —
  /// the event is almost always right and the flag is what needs updating.
  Future<({PersonEvent event, List<ApiWarning> warnings})> addEvent({
    required String personUlid,
    required String eventType,
    String? title,
    String? description,
    String? date,
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.personEvents,
      body: {
        'person_ulid': personUlid,
        'event_type': eventType,
        'title': ?title,
        'description': ?description,
        'date': ?date,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return (
      event: PersonEvent.fromJson(envelope.data!),
      warnings: envelope.warnings,
    );
  }

  /// Adds a relative of [anchorUlid] and returns whatever the server made of it.
  ///
  /// [unionUlid] is only needed when the anchor has more than one marriage. The
  /// server refuses with `UNION_AMBIGUOUS` rather than guessing which one a
  /// child belongs to, and the UI asks.
  Future<AddRelativeResult> addRelative({
    required String anchorUlid,
    required String relation,
    required Map<String, dynamic> person,
    String? unionUlid,
    String? subtype,
    String? customLabel,
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.personRelatives(anchorUlid),
      body: {
        'relation': relation,
        'person': person,
        'union_ulid': ?unionUlid,
        'relationship_subtype': ?subtype,
        'custom_label': ?customLabel,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    final data = envelope.data!;

    return AddRelativeResult(
      person: PersonSummary.fromJson(
        (data['person'] as Map).cast<String, dynamic>(),
      ),
      created: _createdAPerson(data['created']),
      warnings: envelope.warnings,
    );
  }

  /// Did the server actually make a new person?
  ///
  /// It answers with counts, not a flag:
  /// `{"people": 1, "relationships": 1, "unions": 0, "union_children": 0}`.
  /// This was read as `data['created'] as bool?`, which throws a TypeError on
  /// a Map — and a TypeError is not an ApiException, so it went straight past
  /// the screen's catch and left the form frozen on a spinner while the person
  /// it had just created sat happily on the server. Every unit test passed,
  /// because the fake sent the bool the client expected rather than the object
  /// the API sends.
  ///
  /// A bare bool is still accepted: the offline queue replays payloads
  /// recorded before any of this was understood.
  static bool _createdAPerson(Object? raw) => switch (raw) {
    bool value => value,
    Map map => ((map['people'] as num?) ?? 0) > 0,
    _ => true,
  };
}
