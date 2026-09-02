import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/core/errors/api_exception.dart';
import 'package:my_generation/models/change_request.dart';
import 'package:my_generation/models/revision.dart';
import 'package:my_generation/repositories/review_repository.dart';

import 'support/fake_api.dart';

const _person = '01PERSON';
const _editPath = 'PATCH /api/v1/people/$_person';

Map<String, dynamic> _envelope(Map<String, dynamic> data) => {
      'success': true,
      'data': data,
      'meta': const <String, dynamic>{},
      'warnings': const <dynamic>[],
    };

void main() {
  group('editing a person', () {
    test('reports an edit that landed', () async {
      final adapter = FakeAdapter({
        _editPath: [
          FakeReply(200, _envelope({'ulid': _person, 'display_name': 'Thang Dam'})),
        ],
      });

      final outcome = await ReviewRepository(fakeApiClient(adapter)).editPerson(
        ulid: _person,
        changes: const {'first_name': 'Thang'},
      );

      expect(outcome.applied, isTrue);
      expect(outcome.proposal, isNull);
    });

    test('reports an edit that became a suggestion', () async {
      // A 202 is still a success envelope. Reading only `success` and calling
      // it saved is the failure this test exists to prevent: the contributor
      // is told it is done and stops watching for the answer.
      final adapter = FakeAdapter({
        _editPath: [
          FakeReply(202, _envelope({
            'person': null,
            'change_request': {
              'ulid': '01CHANGE',
              'status': 'pending',
              'diff': {
                'first_name': ['Thawng', 'Thang'],
              },
            },
          })),
        ],
      });

      final outcome = await ReviewRepository(fakeApiClient(adapter)).editPerson(
        ulid: _person,
        changes: const {'first_name': 'Thang'},
        reason: 'The gravestone spells it Thang.',
      );

      expect(outcome.applied, isFalse);
      expect(outcome.proposal?.status, 'pending');
      expect(outcome.proposal?.diff.single.before, 'Thawng');
      expect(outcome.proposal?.diff.single.after, 'Thang');
    });

    test('sends the reason with the change', () async {
      final adapter = FakeAdapter({
        _editPath: [FakeReply(200, _envelope({'ulid': _person, 'display_name': 'X'}))],
      });

      await ReviewRepository(fakeApiClient(adapter)).editPerson(
        ulid: _person,
        changes: const {'first_name': 'Thang'},
        reason: 'The gravestone spells it Thang.',
      );

      expect(
        adapter.received.single.data['reason'],
        'The gravestone spells it Thang.',
      );
    });
  });

  group('the review queue', () {
    test('takes review authority from the server, not a role name', () async {
      // Authority is scoped: "clan-admin" does not say which clan. Only the
      // server can answer whether this account reviews anything.
      final adapter = FakeAdapter({
        'GET /api/v1/change-requests': [
          FakeReply(200, {
            'success': true,
            'data': const <dynamic>[],
            'meta': {'can_review': true, 'filter': 'review'},
            'warnings': const <dynamic>[],
          }),
        ],
      });

      final queue = await ReviewRepository(fakeApiClient(adapter))
          .changeRequests(filter: 'review');

      expect(queue.canReview, isTrue);
      expect(queue.requests, isEmpty);
    });

    test('reads a proposal with its diff', () async {
      final adapter = FakeAdapter({
        'GET /api/v1/change-requests': [
          FakeReply(200, {
            'success': true,
            'data': [
              {
                'ulid': '01CHANGE',
                'status': 'pending',
                'operation': 'update',
                'reason': 'The gravestone spells it Thang.',
                'diff': [
                  {
                    'field': 'first_name',
                    'label': 'First name',
                    'before': 'Thawng',
                    'after': 'Thang',
                  },
                ],
                'target': {'ulid': _person, 'label': 'Thawng Dam'},
                'requested_by': {'ulid': '01USER', 'name': 'Cin Hlei'},
                'submitted_at': '2026-09-03T06:00:00+00:00',
              },
            ],
            'meta': {'can_review': true},
            'warnings': const <dynamic>[],
          }),
        ],
      });

      final queue = await ReviewRepository(fakeApiClient(adapter))
          .changeRequests(filter: 'review');

      final request = queue.requests.single;

      expect(request.targetLabel, 'Thawng Dam');
      expect(request.requestedByName, 'Cin Hlei');
      expect(request.isPending, isTrue);
      expect(request.diff.single.beforeText, 'Thawng');
      expect(request.submittedAt, isNotNull);
    });

    test('carries the three-way diff when a record moved first', () async {
      final adapter = FakeAdapter({
        'POST /api/v1/change-requests/01CHANGE/approve': [
          FakeReply(409, {
            'success': false,
            'message': 'This record changed after the request was submitted.',
            'code': 'CHANGE_REQUEST_SUPERSEDED',
            'errors': {
              'conflicts': ['first_name'],
            },
            'meta': {
              'conflicts': [
                {'field': 'first_name', 'was': 'Thawng', 'now': 'Thawn'},
              ],
            },
          }),
        ],
      });

      // Naming the field is not enough to resolve a conflict; the reviewer
      // needs both values to decide.
      await expectLater(
        ReviewRepository(fakeApiClient(adapter)).approve('01CHANGE'),
        throwsA(
          isA<ApiException>()
              .having((e) => e.code, 'code', 'CHANGE_REQUEST_SUPERSEDED')
              .having(
                (e) => (e.meta['conflicts'] as List).first,
                'first conflict',
                {'field': 'first_name', 'was': 'Thawng', 'now': 'Thawn'},
              ),
        ),
      );
    });
  });

  group('history', () {
    test('separates no changes from withheld changes', () async {
      const empty = History(entries: [], withheld: false);
      const hidden = History.withheldFrom();

      expect(empty.isEmpty, isTrue);
      expect(hidden.isEmpty, isFalse);
      expect(hidden.withheld, isTrue);
    });

    test('reads a row-level entry that has no field', () async {
      // "Record added" has no before and after. Treating every entry as a
      // field change drops the entry that says when the person first appeared.
      final entry = RevisionEntry.fromJson({
        'id': 1,
        'label': 'Record added',
        'action': 'created',
        'field': null,
        'at': '2026-09-03T06:00:00+00:00',
      });

      expect(entry.isFieldChange, isFalse);
      expect(entry.label, 'Record added');
    });

    test('reads a boolean as a person would say it', () async {
      // The ledger stores the column's value. "Living: false -> true" says
      // nothing to a family about what was actually corrected.
      final entry = RevisionEntry.fromJson({
        'id': 1,
        'label': 'Living',
        'action': 'updated',
        'field': 'is_living',
        'before': false,
        'after': true,
      });

      expect(entry.beforeText, 'No');
      expect(entry.afterText, 'Yes');
    });

    test('reads an enum value without its underscores', () async {
      final entry = RevisionEntry.fromJson({
        'id': 1,
        'label': 'Verification',
        'action': 'updated',
        'field': 'verification_status',
        'before': 'unverified',
        'after': 'needs_review',
      });

      expect(entry.beforeText, 'Unverified');
      expect(entry.afterText, 'Needs Review');
    });

    test('shows an empty value as a dash rather than a blank', () async {
      final entry = RevisionEntry.fromJson({
        'id': 1,
        'label': 'Nickname',
        'action': 'updated',
        'field': 'nickname',
        'before': null,
        'after': 'Pu Thang',
      });

      // "Nothing recorded" is a fact; a blank looks like a rendering bug.
      expect(entry.beforeText, '—');
      expect(entry.afterText, 'Pu Thang');
    });
  });

  group('a proposal in the queue', () {
    test('says what happened to it in plain words', () {
      ChangeRequestSummary withStatus(String status) =>
          ChangeRequestSummary.fromJson({
            'ulid': '01CHANGE',
            'status': status,
            'operation': 'update',
            'diff': const <dynamic>[],
          });

      expect(withStatus('pending').statusLabel, 'Waiting for review');
      expect(withStatus('superseded').statusLabel, 'The record changed first');
      expect(withStatus('rejected').statusLabel, 'Not accepted');
      expect(withStatus('approved').isPending, isFalse);
    });
  });
}
