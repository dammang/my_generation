import '../core/constants/api_paths.dart';
import '../core/network/api_client.dart';
import '../models/change_request.dart';
import '../models/dispute.dart';
import '../models/person_detail.dart';
import '../models/revision.dart';

/// A page of proposals, and whether this account reviews anything at all.
class ReviewQueue {
  const ReviewQueue({required this.requests, required this.canReview});

  final List<ChangeRequestSummary> requests;

  /// Comes from the server. The app must not decide from a role name whether
  /// somebody may review — authority is scoped, and a name does not carry that.
  final bool canReview;
}

class ReviewRepository {
  ReviewRepository(this._api);

  final ApiClient _api;

  Future<ReviewQueue> changeRequests({String filter = 'mine'}) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.changeRequests,
      query: {'filter': filter},
      parse: (data) => (data as List?) ?? const [],
    );

    return ReviewQueue(
      requests: (envelope.data ?? const [])
          .whereType<Map>()
          .map((e) => ChangeRequestSummary.fromJson(e.cast<String, dynamic>()))
          .toList(growable: false),
      canReview: envelope.meta['can_review'] as bool? ?? false,
    );
  }

  Future<ChangeRequestSummary> approve(String ulid, {String? comment}) =>
      _decide(ApiPaths.approveChange(ulid), comment);

  Future<ChangeRequestSummary> reject(String ulid, {String? comment}) =>
      _decide(ApiPaths.rejectChange(ulid), comment);

  Future<ChangeRequestSummary> withdraw(String ulid) =>
      _decide(ApiPaths.withdrawChange(ulid), null);

  Future<ChangeRequestSummary> _decide(String path, String? comment) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      path,
      body: {'comment': ?comment},
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return ChangeRequestSummary.fromJson(envelope.data!);
  }

  Future<History> history(String personUlid) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.personRevisions(personUlid),
      parse: (data) => (data as List?) ?? const [],
    );

    return History(
      entries: (envelope.data ?? const [])
          .whereType<Map>()
          .map((e) => RevisionEntry.fromJson(e.cast<String, dynamic>()))
          .toList(growable: false),
      withheld: envelope.meta['withheld'] as bool? ?? false,
    );
  }

  Future<List<Dispute>> disputes(String personUlid) async {
    final envelope = await _api.get<List<dynamic>>(
      ApiPaths.personDisputes(personUlid),
      parse: (data) => (data as List?) ?? const [],
    );

    return (envelope.data ?? const [])
        .whereType<Map>()
        .map((e) => Dispute.fromJson(e.cast<String, dynamic>()))
        .toList(growable: false);
  }

  Future<Dispute> raiseDispute({
    required String personUlid,
    required String field,
    required String claimedValue,
    String? rationale,
  }) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.disputes,
      body: {
        'person_ulid': personUlid,
        'field': field,
        'claimed_value': claimedValue,
        'rationale': ?rationale,
      },
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return Dispute.fromJson(envelope.data!);
  }

  Future<PersonDetail> verify(String personUlid, {bool verified = true, String? note}) async {
    final envelope = await _api.post<Map<String, dynamic>>(
      ApiPaths.personVerify(personUlid),
      body: {'verified': verified, 'note': ?note},
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    return PersonDetail.fromJson(envelope.data!);
  }

  /// Edits a person — which either lands or becomes a proposal.
  ///
  /// The server decides which, based on whether the record is verified and what
  /// this account may do in that scope. The caller must not guess: showing
  /// "saved" for something awaiting review is the worst thing this screen can
  /// do, because the contributor stops watching for it.
  Future<EditOutcome> editPerson({
    required String ulid,
    required Map<String, dynamic> changes,
    String? reason,
  }) async {
    final envelope = await _api.patch<Map<String, dynamic>>(
      ApiPaths.person(ulid),
      body: {...changes, 'reason': ?reason},
      parse: (data) => (data as Map).cast<String, dynamic>(),
    );

    final data = envelope.data!;
    final proposal = data['change_request'];

    if (proposal is Map) {
      return EditOutcome(
        applied: false,
        proposal: ChangeRequestSummary.fromJson({
          'ulid': proposal['ulid'],
          'status': proposal['status'],
          'diff': _diffEntries(proposal['diff']),
        }),
      );
    }

    return const EditOutcome.applied();
  }

  /// The 202 body carries the raw `{field: [before, after]}` map rather than
  /// the reviewer-facing shape, so it is converted here rather than teaching
  /// the model two formats.
  static List<Map<String, dynamic>> _diffEntries(Object? raw) {
    if (raw is! Map) return const [];

    return raw.entries.map((entry) {
      final pair = entry.value;

      return <String, dynamic>{
        'field': entry.key.toString(),
        'label': entry.key.toString().replaceAll('_', ' '),
        'before': pair is List && pair.isNotEmpty ? pair.first : null,
        'after': pair is List && pair.length > 1 ? pair[1] : null,
      };
    }).toList(growable: false);
  }
}
