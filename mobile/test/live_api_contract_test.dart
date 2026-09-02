@Tags(['live'])
library;

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/core/constants/api_paths.dart';
import 'package:my_generation/core/network/api_envelope.dart';
import 'package:my_generation/models/api_user.dart';
import 'package:my_generation/models/person_summary.dart';

/// Checks the client's parsing against a real running server.
///
/// Unit tests prove the models handle the JSON I *believe* the API sends. This
/// proves they handle what it actually sends — the two diverge the moment a
/// field is renamed on one side, and no amount of mocking catches that.
///
/// Opt-in, because a plain `flutter test` must not depend on a running server:
///
///   cd .. && php artisan serve
///   flutter test --dart-define=LIVE_API=true test/live_api_contract_test.dart
///
/// They also skip themselves if the API turns out to be unreachable.
const bool _enabled = bool.fromEnvironment('LIVE_API');

void main() {
  late Dio dio;
  bool reachable = false;

  setUpAll(() async {
    if (!_enabled) return;

    dio = Dio(BaseOptions(
      baseUrl: 'http://127.0.0.1:8000',
      headers: const {'Accept': 'application/json'},
      validateStatus: (status) => status != null && status < 500,
      connectTimeout: const Duration(seconds: 3),
    ));

    try {
      final response = await dio.get(ApiPaths.health);
      reachable = response.statusCode == 200;
    } catch (_) {
      reachable = false;
    }
  });

  String? cachedToken;

  /// Signed in once and reused.
  ///
  /// The auth endpoints are throttled to five attempts a minute per address —
  /// deliberately, since that is what stops credential stuffing — so a suite
  /// that signs in per test exhausts the limit and fails on its own protection.
  /// A real client signs in once too.
  Future<String> signIn() async {
    if (cachedToken != null) return cachedToken!;

    final response = await dio.post(ApiPaths.login, data: {
      'email': 'admin@mygeneration.test',
      'password': 'password',
      'device_name': 'contract-test',
    });

    final body = (response.data as Map).cast<String, dynamic>();

    if (body['success'] != true) {
      fail('Sign-in failed (${response.statusCode}): ${body['message']}');
    }

    return cachedToken = (body['data'] as Map)['token'] as String;
  }

  const skipReason = _enabled ? null : 'Set --dart-define=LIVE_API=true and run the API';

  test('the health endpoint answers in the expected envelope', () async {
    if (!reachable) return;

    final response = await dio.get(ApiPaths.health);
    final envelope = ApiEnvelope.fromJson<Map<String, dynamic>>(
      (response.data as Map).cast<String, dynamic>(),
      (data) => (data as Map).cast<String, dynamic>(),
    );

    expect(envelope.success, isTrue);
    expect(envelope.data!['status'], 'ok');
    expect(envelope.data!['version'], 'v1');
  }, skip: skipReason);

  test('an unauthenticated request is refused in the error envelope', () async {
    if (!reachable) return;

    final response = await dio.get(ApiPaths.me);
    final json = (response.data as Map).cast<String, dynamic>();

    expect(response.statusCode, 401);
    expect(json['success'], isFalse);
    expect(json['code'], 'UNAUTHENTICATED');
  }, skip: skipReason);

  test('sign in returns a token the client can store', () async {
    if (!reachable) return;

    final token = await signIn();

    expect(token, isNotEmpty);
    expect(token, contains('|'), reason: 'Sanctum personal access tokens are id|plaintext');
  }, skip: skipReason);

  test('/auth/me parses into ApiUser with real scopes and permissions', () async {
    if (!reachable) return;

    final token = await signIn();

    final response = await dio.get(
      ApiPaths.me,
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    final envelope = ApiEnvelope.fromJson<ApiUser>(
      (response.data as Map).cast<String, dynamic>(),
      (data) => ApiUser.fromJson((data as Map).cast<String, dynamic>()),
    );

    final user = envelope.data!;

    expect(envelope.success, isTrue);
    expect(user.email, 'admin@mygeneration.test');
    expect(user.isSuperAdmin, isTrue);
    expect(user.can('anything'), isTrue, reason: 'A super admin needs no listed permission');
    // A user is not a person: the seeded admin has claimed nobody.
    expect(user.hasClaimedPerson, isFalse);
  }, skip: skipReason);

  test('people parse into PersonSummary, already masked by the server', () async {
    if (!reachable) return;

    final token = await signIn();

    final response = await dio.get(
      ApiPaths.people,
      queryParameters: {'per_page': 10},
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );

    final json = (response.data as Map).cast<String, dynamic>();
    final people = (json['data'] as List)
        .map((p) => PersonSummary.fromJson((p as Map).cast<String, dynamic>()))
        .toList();

    expect(people, isNotEmpty, reason: 'The demo seeder provides people');
    expect(json['meta']['per_page'], 10);

    // Every person carries the fields the UI branches on, whatever the mask did.
    for (final person in people) {
      expect(person.ulid, isNotEmpty);
      expect(person.displayName, isNotEmpty);
    }

    // The seeded demo includes deceased ancestors with uncertain dates, and the
    // client must render the source's own wording rather than reformatting.
    final dated = people.where((p) => p.birthDisplay != null);
    expect(dated, isNotEmpty);
  }, skip: skipReason);

  test('a tree response parses into layered people and edges', () async {
    if (!reachable) return;

    final token = await signIn();
    final auth = Options(headers: {'Authorization': 'Bearer $token'});

    final list = await dio.get(ApiPaths.people, queryParameters: {'per_page': 1}, options: auth);
    final ulid = (list.data['data'] as List).first['ulid'] as String;

    final response = await dio.get(
      ApiPaths.tree(ulid),
      queryParameters: {'ancestors': 3, 'descendants': 2},
      options: auth,
    );

    final json = (response.data as Map).cast<String, dynamic>();
    final data = (json['data'] as Map).cast<String, dynamic>();
    final meta = (json['meta'] as Map).cast<String, dynamic>();

    expect(data['focus'], ulid);
    expect(data['people'], isA<List>());
    expect(data['unions'], isA<List>());
    expect(data['edges'], isA<List>());
    expect(meta['node_count'], isA<int>());
    expect(meta['truncated'], isA<bool>());

    // depth is the layer: negative up, positive down.
    final people = (data['people'] as List)
        .map((p) => PersonSummary.fromJson((p as Map).cast<String, dynamic>()))
        .toList();

    expect(people.any((p) => p.depth == 0), isTrue, reason: 'The focus sits at depth 0');
  }, skip: skipReason);
}
