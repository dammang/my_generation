import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/core/errors/api_exception.dart';
import 'package:my_generation/core/network/api_envelope.dart';
import 'package:my_generation/models/api_user.dart';
import 'package:my_generation/models/person_summary.dart';

void main() {
  group('ApiEnvelope', () {
    test('parses a successful response with no warnings', () {
      final envelope = ApiEnvelope.fromJson<Map<String, dynamic>>({
        'success': true,
        'data': {'ulid': 'X'},
        'warnings': [],
      }, (data) => (data as Map).cast<String, dynamic>());

      expect(envelope.success, isTrue);
      expect(envelope.data!['ulid'], 'X');
      expect(envelope.hasWarnings, isFalse);
    });

    test('carries warnings alongside a successful write', () {
      // Genealogy writes routinely succeed and carry doubt; the client must
      // never treat a warning as a failure.
      final envelope = ApiEnvelope.fromJson<Map<String, dynamic>>({
        'success': true,
        'data': <String, dynamic>{},
        'warnings': [
          {
            'code': 'CHILD_BORN_AFTER_PARENT_DEATH',
            'message': "Born 20 years after Za Kam's recorded death.",
            'field': 'birth_date',
          },
        ],
      }, (data) => (data as Map).cast<String, dynamic>());

      expect(envelope.success, isTrue);
      expect(envelope.warnings.single.code, 'CHILD_BORN_AFTER_PARENT_DEATH');
      expect(envelope.warnings.single.field, 'birth_date');
    });

    test('reads cursor pagination meta', () {
      final envelope = ApiEnvelope.fromJson<List<dynamic>>({
        'success': true,
        'data': [],
        'meta': {'per_page': 25, 'next_cursor': 'abc', 'has_more': true},
      }, (data) => data as List<dynamic>);

      expect(envelope.meta['next_cursor'], 'abc');
      expect(envelope.meta['has_more'], isTrue);
    });
  });

  group('ApiException', () {
    test('exposes field errors for inline form messages', () {
      const error = ApiException(
        message: 'The given data was invalid.',
        statusCode: 422,
        code: 'VALIDATION_FAILED',
        errors: {
          'email': ['These credentials do not match our records.'],
        },
      );

      expect(error.isValidation, isTrue);
      expect(error.errorFor('email'), 'These credentials do not match our records.');
      expect(error.errorFor('password'), isNull);
    });

    test('classifies the statuses the UI branches on', () {
      expect(const ApiException(message: '', statusCode: 401).isUnauthenticated, isTrue);
      expect(const ApiException(message: '', statusCode: 404).isNotFound, isTrue);
      expect(const ApiException(message: '', statusCode: 429).isThrottled, isTrue);
      // No status at all means the request never reached the server.
      expect(const ApiException(message: '').isOffline, isTrue);
    });

    test('a server fault is not repeated to the person holding the phone', () {
      // What the misconfigured server actually returned during a real run.
      final error = ApiException.fromDio(
        DioException(
          requestOptions: RequestOptions(path: '/api/v1/auth/firebase'),
          response: Response(
            requestOptions: RequestOptions(path: '/api/v1/auth/firebase'),
            statusCode: 500,
            data: const {'message': 'Unable to create an API client without credentials'},
          ),
        ),
      );

      expect(error.message, isNot(contains('API client')));
      expect(error.message, 'Something went wrong on our end. Please try again in a moment.');
      // Still recoverable for whoever has to fix the server.
      expect(error.serverDetail, 'Unable to create an API client without credentials');
      expect(error.toString(), contains('Unable to create an API client'));
    });

    test('a 4xx message is shown as written, because it is for the reader', () {
      final error = ApiException.fromDio(
        DioException(
          requestOptions: RequestOptions(path: '/api/v1/auth/login'),
          response: Response(
            requestOptions: RequestOptions(path: '/api/v1/auth/login'),
            statusCode: 422,
            data: const {'message': 'That email is already registered.'},
          ),
        ),
      );

      expect(error.message, 'That email is already registered.');
      expect(error.serverDetail, isNull);
    });
  });

  group('PersonSummary', () {
    test('reads a masked living person without inventing dates', () {
      final person = PersonSummary.fromJson({
        'ulid': '01HZX',
        'display_name': 'Thawng Dam',
        'gender': 'male',
        'is_living': true,
        'redacted': true,
        'birth': null,
        'death': null,
      });

      expect(person.redacted, isTrue);
      expect(person.birthDisplay, isNull);
      expect(person.lifespan, isNull);
      // The name and position survive: hiding the node would misrepresent
      // everyone else's lineage.
      expect(person.displayName, 'Thawng Dam');
    });

    test('formats a lifespan from the server-supplied display strings', () {
      final person = PersonSummary.fromJson({
        'ulid': '01HZX',
        'display_name': 'Kin Tun',
        'gender': 'male',
        'is_living': false,
        'redacted': false,
        'birth': {'display': '1898', 'year': 1898},
        'death': {'display': '1961', 'year': 1961},
      });

      expect(person.lifespan, '1898–1961');
    });

    test('keeps the source wording rather than reformatting a date', () {
      // "abt. 1902" is evidence. The client renders what the server sent.
      final person = PersonSummary.fromJson({
        'ulid': '01HZX',
        'display_name': 'Za Vung',
        'gender': 'female',
        'is_living': false,
        'redacted': false,
        'birth': {'display': 'abt. 1902', 'year': 1902},
      });

      expect(person.birthDisplay, 'abt. 1902');
      expect(person.lifespan, 'b. abt. 1902');
    });

    test('reads a placeholder node', () {
      final person = PersonSummary.fromJson({
        'ulid': '01HZX',
        'display_name': 'Private',
        'gender': 'unknown',
        'is_living': true,
        'redacted': true,
        'placeholder': true,
      });

      expect(person.placeholder, isTrue);
      expect(person.displayName, 'Private');
    });

    test('reads tree depth as a signed layer', () {
      expect(
        PersonSummary.fromJson({
          'ulid': 'X',
          'display_name': 'A',
          'gender': 'male',
          'is_living': false,
          'redacted': false,
          'depth': -2,
        }).depth,
        -2,
      );
    });
  });

  group('ApiUser', () {
    test('reads scopes and permissions from /auth/me', () {
      final user = ApiUser.fromJson({
        'ulid': '01HU',
        'name': 'Dam Mang',
        'email': 'dam@example.com',
        'locale': 'en',
        'status': 'active',
        'email_verified': true,
        'is_super_admin': false,
        'permissions': ['people.view', 'people.create'],
        'scopes': {
          'tribe_ids': [1],
          'clan_ids': [3, 4],
          'branch_ids': [],
        },
        'person': null,
      });

      expect(user.can('people.create'), isTrue);
      expect(user.can('people.verify'), isFalse);
      expect(user.tribeIds, [1]);
      expect(user.clanIds, [3, 4]);
      // A user is not a person.
      expect(user.hasClaimedPerson, isFalse);
    });

    test('a super admin can do anything without listing it', () {
      final user = ApiUser.fromJson({
        'ulid': '01HU',
        'name': 'Admin',
        'email': 'a@b.c',
        'is_super_admin': true,
        'permissions': <String>[],
        'scopes': <String, dynamic>{},
      });

      expect(user.can('anything.at.all'), isTrue);
    });
  });
}
