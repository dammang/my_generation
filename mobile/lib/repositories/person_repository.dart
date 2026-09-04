import 'package:dio/dio.dart';

import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
import '../core/network/api_envelope.dart';
import '../models/family_bundle.dart';
import '../models/media_item.dart';
import '../models/person_detail.dart';
import '../models/person_event.dart';
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
      if (caption != null && caption.trim().isNotEmpty) 'caption': caption.trim(),
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
      created: data['created'] as bool? ?? true,
      warnings: envelope.warnings,
    );
  }
}
