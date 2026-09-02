import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/core/errors/api_exception.dart';
import 'package:my_generation/models/api_user.dart';
import 'package:my_generation/providers/app_providers.dart';
import 'package:my_generation/providers/auth_provider.dart';
import 'package:my_generation/repositories/auth_repository.dart';

/// What happens between launching the app and knowing who is using it.
///
/// The rule this pins down: a stored token is not proof of a session, but a
/// failure to check it is not proof of its absence either. Signing somebody out
/// because their train went into a tunnel would be its own bug.
class _FakeAuthRepository implements AuthRepository {
  _FakeAuthRepository({this.token, this.user, this.error});

  final String? token;
  final ApiUser? user;
  final Object? error;

  int meCalls = 0;
  bool loggedOut = false;

  @override
  Future<bool> hasToken() async => token != null;

  @override
  Future<ApiUser> me() async {
    meCalls++;
    if (error != null) throw error!;
    return user!;
  }

  @override
  Future<ApiUser> login({
    required String email,
    required String password,
    String deviceName = 'mobile',
  }) async {
    if (error != null) throw error!;
    return user!;
  }

  @override
  Future<ApiUser> register({
    required String name,
    required String email,
    required String password,
    String deviceName = 'mobile',
  }) async =>
      user!;

  @override
  Future<void> logout() async => loggedOut = true;
}

ApiUser _user() => const ApiUser(
      ulid: '01U',
      name: 'Dam Mang',
      email: 'dam@example.com',
      locale: 'en',
      status: 'active',
      emailVerified: true,
      isSuperAdmin: false,
      permissions: ['people.view'],
      tribeIds: [1],
      clanIds: [],
      branchIds: [],
    );

ProviderContainer _containerWith(AuthRepository repository) {
  final container = ProviderContainer(
    overrides: [authRepositoryProvider.overrideWithValue(repository)],
  );
  addTearDown(container.dispose);
  return container;
}

void main() {
  test('a stored token is verified against the server, not trusted', () async {
    final repository = _FakeAuthRepository(token: '1|abc', user: _user());
    final container = _containerWith(repository);

    await container.read(authProvider.notifier).restore();

    // The point: it asked. A token may have been revoked or the account
    // suspended since it was issued.
    expect(repository.meCalls, 1);
    expect(container.read(authProvider), isA<AuthSignedIn>());
  });

  test('a session survives a restart', () async {
    final container = _containerWith(
      _FakeAuthRepository(token: '1|abc', user: _user()),
    );

    expect(container.read(authProvider), isA<AuthUnknown>());

    await container.read(authProvider.notifier).restore();

    final state = container.read(authProvider);
    expect(state, isA<AuthSignedIn>());
    expect((state as AuthSignedIn).user.email, 'dam@example.com');
  });

  test('no stored token means signed out, without calling the server', () async {
    final repository = _FakeAuthRepository();
    final container = _containerWith(repository);

    await container.read(authProvider.notifier).restore();

    expect(repository.meCalls, 0);
    expect(container.read(authProvider), isA<AuthSignedOut>());
  });

  test('a revoked token signs the person out with an explanation', () async {
    final container = _containerWith(
      _FakeAuthRepository(
        token: '1|revoked',
        error: const ApiException(message: 'Unauthenticated.', statusCode: 401),
      ),
    );

    await container.read(authProvider.notifier).restore();

    final state = container.read(authProvider);
    expect(state, isA<AuthSignedOut>());
    expect((state as AuthSignedOut).message, contains('session has ended'));
  });

  test('being offline does not sign anybody out', () async {
    // The token may well still be good. Losing a session over a bad connection
    // would be worse than showing a retry.
    final container = _containerWith(
      _FakeAuthRepository(
        token: '1|abc',
        error: const ApiException(message: 'Cannot reach My Generation.'),
      ),
    );

    await expectLater(
      container.read(authProvider.notifier).restore(),
      throwsA(isA<ApiException>()),
    );

    expect(
      container.read(authProvider),
      isA<AuthUnknown>(),
      reason: 'Still unknown — the app retries rather than discarding the session',
    );
  });

  test('a 401 from any request ends the session once', () async {
    final container = _containerWith(
      _FakeAuthRepository(token: '1|abc', user: _user()),
    );

    await container.read(authProvider.notifier).restore();
    expect(container.read(authProvider), isA<AuthSignedIn>());

    // Simulates the auth interceptor seeing a rejected token mid-session.
    container.read(unauthenticatedSignalProvider.notifier).raise();

    expect(container.read(authProvider), isA<AuthSignedOut>());
  });

  test('signing out clears local state', () async {
    final repository = _FakeAuthRepository(token: '1|abc', user: _user());
    final container = _containerWith(repository);

    await container.read(authProvider.notifier).restore();
    await container.read(authProvider.notifier).signOut();

    expect(repository.loggedOut, isTrue);
    expect(container.read(authProvider), isA<AuthSignedOut>());
  });

  test('registration adopts the user without a second sign-in', () async {
    // Registration already returns a token; signing in again would spend an
    // attempt against the five-a-minute auth throttle for no reason.
    final repository = _FakeAuthRepository(user: _user());
    final container = _containerWith(repository);

    container.read(authProvider.notifier).adopt(_user());

    expect(container.read(authProvider), isA<AuthSignedIn>());
    expect(repository.meCalls, 0);
  });
}
