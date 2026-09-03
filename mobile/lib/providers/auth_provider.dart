import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';

import '../core/errors/api_exception.dart';
import '../models/api_user.dart';
import '../repositories/auth_repository.dart';
import '../services/firebase_sign_in_service.dart';
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

  FirebaseSignInService get _firebase => ref.read(firebaseSignInProvider);

  /// Signs in with Google, then exchanges the result for a session here.
  Future<void> signInWithGoogle() => _exchange(_firebase.withGoogle());

  Future<void> signInWithApple() => _exchange(_firebase.withApple());

  Future<void> signInWithFirebasePassword({required String email, required String password}) =>
      _exchange(_firebase.withPassword(email: email, password: password));

  Future<void> registerWithFirebase({
    required String name,
    required String email,
    required String password,
  }) => _exchange(_firebase.registerWithPassword(name: name, email: email, password: password));

  Future<void> _exchange(Future<String> idToken) async {
    final token = await idToken;

    state = AuthSignedIn(
      await _repository.exchangeFirebaseToken(
        idToken: token,
        locale: PlatformDispatcher.instance.locale.languageCode,
      ),
    );

    // Asked for now rather than on first launch: somebody who has just joined
    // their family's archive has a reason to say yes, and on iOS the question
    // is only ever asked once.
    unawaited(_registerForPush());
  }

  Future<void> _registerForPush() async {
    try {
      await ref.read(pushServiceProvider).register();
    } catch (error) {
      // Never surface this. Somebody has just signed in successfully; a push
      // registration that did not take is not their problem to solve.
      if (kDebugMode) debugPrint('Push registration failed: $error');
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
    // Before the token goes: the next person to hold this phone must not
    // receive notifications about a family they have nothing to do with.
    try {
      await ref.read(pushServiceProvider).unregister();
    } catch (error) {
      if (kDebugMode) debugPrint('Could not unregister this device: $error');
    }

    // Two sessions, ended together. Leaving the Firebase one behind means the
    // next sign-in silently reuses the previous account without asking.
    //
    // Guarded, like everything else touching Firebase: somebody pressing sign
    // out on a shared phone must end up signed out whether or not a third
    // party is reachable. Local state is cleared below regardless.
    try {
      await _firebase.signOut();
    } catch (error) {
      if (kDebugMode) debugPrint('Firebase sign-out failed: $error');
    }

    await _repository.logout();
    state = const AuthSignedOut();
  }

  /// Re-reads the account, picking up membership and role changes.
  /// Sends the verification email again, then refreshes the account.
  ///
  /// The refresh is what makes the banner disappear once somebody confirms in
  /// another window and comes back — without it the only way to notice is to
  /// sign out and in again.
  Future<void> resendVerificationEmail() async {
    await _repository.resendVerificationEmail();
    await refresh();
  }

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
