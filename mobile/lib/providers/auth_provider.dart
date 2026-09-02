import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';

import '../core/errors/api_exception.dart';
import '../models/api_user.dart';
import '../repositories/auth_repository.dart';
import 'app_providers.dart';

/// Where the app is, as far as the account is concerned.
sealed class AuthState {
  const AuthState();
}

/// Before the stored token has been checked. The router waits here rather than
/// flashing the sign-in screen at somebody who is already signed in.
class AuthUnknown extends AuthState {
  const AuthUnknown();
}

class AuthSignedOut extends AuthState {
  const AuthSignedOut({this.message});

  /// Set when the session ended for a reason worth explaining.
  final String? message;
}

class AuthSignedIn extends AuthState {
  const AuthSignedIn(this.user, {this.offline = false});

  final ApiUser user;

  /// Entered on a remembered account because the server could not be reached.
  ///
  /// Its permissions are as stale as the device is old, which is fine: they
  /// decide what the app *offers*, never what is allowed. Every write is still
  /// authorised by the server when it finally arrives.
  final bool offline;
}

class AuthNotifier extends Notifier<AuthState> {
  @override
  AuthState build() {
    // A 401 from anywhere ends the session once, centrally, rather than every
    // failing request racing to send the person back to sign-in.
    ref.listen<int>(unauthenticatedSignalProvider, (previous, next) {
      if (previous != null && next > previous) {
        state = const AuthSignedOut(message: 'Your session has ended. Please sign in again.');
      }
    });

    return const AuthUnknown();
  }

  AuthRepository get _repository => ref.read(authRepositoryProvider);

  /// Called once at startup.
  ///
  /// A stored token is not proof of a session — it may have been revoked, or
  /// the account suspended — so it is verified against the server before the
  /// app treats anybody as signed in.
  Future<void> restore() async {
    await _seedDevTokenIfDebug();

    if (!await _repository.hasToken()) {
      state = const AuthSignedOut();
      return;
    }

    try {
      state = AuthSignedIn(await _repository.me());
    } on ApiException catch (error) {
      if (error.isUnauthenticated) {
        state = const AuthSignedOut(message: 'Your session has ended. Please sign in again.');
        return;
      }

      // Offline: the token may well still be good, so the person is not signed
      // out over a bad connection — and more importantly they are not locked
      // out of a tree already saved on their own device. Being unable to open
      // the app on a plane is the offline failure that matters most, and it is
      // the one a login gate causes.
      final remembered = await _repository.cachedAccount();

      if (error.isOffline && remembered != null) {
        state = AuthSignedIn(remembered, offline: true);
        return;
      }

      rethrow;
    }
  }

  Future<void> signIn({required String email, required String password}) async {
    state = AuthSignedIn(await _repository.login(email: email, password: password));
  }

  /// Accepts a user the repository has already authenticated — registration
  /// signs in as part of creating the account, so it should not sign in twice
  /// and spend an attempt against the auth throttle.
  void adopt(ApiUser user) => state = AuthSignedIn(user);

  Future<void> signOut() async {
    await _repository.logout();
    state = const AuthSignedOut();
  }

  /// Re-reads the account, picking up membership and role changes.
  Future<void> refresh() async {
    if (state is! AuthSignedIn) return;
    state = AuthSignedIn(await _repository.me());
  }

  /// Debug-only convenience: start signed in from a token passed at build time.
  ///
  /// Gated on kDebugMode rather than on the define being present, so a release
  /// build carrying one by accident still ignores it.
  Future<void> _seedDevTokenIfDebug() async {
    if (!kDebugMode || Env.devToken.isEmpty) return;
    if (await _repository.hasToken()) return;

    await ref.read(secureStorageProvider).writeToken(Env.devToken);
    ref.read(apiClientProvider).forgetToken();
  }

  void forceSignedOut(String message) => state = AuthSignedOut(message: message);
}

final authProvider = NotifierProvider<AuthNotifier, AuthState>(AuthNotifier.new);
