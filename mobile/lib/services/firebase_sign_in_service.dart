import 'dart:io' show Platform;

import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/foundation.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

/// Something the person can be told, rather than a provider's error code.
class SignInFailure implements Exception {
  const SignInFailure(this.message, {this.cancelled = false});

  /// They closed the sheet. Not an error, and showing one as though it were
  /// makes the app look broken when nothing went wrong.
  const SignInFailure.cancelled()
      : message = '',
        cancelled = true;

  final String message;
  final bool cancelled;
}

/// Signing in with Firebase, and nothing else.
///
/// This deliberately stops at the ID token. What that token means here — which
/// tribes somebody belongs to, what they may edit, whether an administrator has
/// suspended them — is the server's business, and asking Firebase about it
/// would be asking the wrong system.
class FirebaseSignInService {
  FirebaseSignInService({FirebaseAuth? auth}) : _auth = auth ?? FirebaseAuth.instance;

  final FirebaseAuth _auth;

  bool get appleAvailable => !kIsWeb && Platform.isIOS;

  /// Google, through the platform's own account picker.
  Future<String> withGoogle() async {
    try {
      await GoogleSignIn.instance.initialize();

      final account = await GoogleSignIn.instance.authenticate();
      final idToken = account.authentication.idToken;

      if (idToken == null) {
        throw const SignInFailure(
          'Google did not return a sign-in token. Please try again.',
        );
      }

      final credential = GoogleAuthProvider.credential(idToken: idToken);

      return _idTokenFrom(await _auth.signInWithCredential(credential));
    } on GoogleSignInException catch (error) {
      if (error.code == GoogleSignInExceptionCode.canceled) {
        throw const SignInFailure.cancelled();
      }

      throw SignInFailure(_readable(error.code.name, error.description));
    } on FirebaseAuthException catch (error) {
      throw SignInFailure(_firebaseMessage(error));
    }
  }

  /// Apple. Mandatory on iOS wherever Google is offered, by Apple's own rules.
  Future<String> withApple() async {
    try {
      final apple = await SignInWithApple.getAppleIDCredential(
        scopes: const [
          AppleIDAuthorizationScopes.email,
          AppleIDAuthorizationScopes.fullName,
        ],
      );

      final credential = OAuthProvider('apple.com').credential(
        idToken: apple.identityToken,
        accessToken: apple.authorizationCode,
      );

      final result = await _auth.signInWithCredential(credential);

      // Apple sends the name exactly once, on the very first authorisation,
      // and never again. Not capturing it here means the account is called
      // by its email address forever.
      final given = apple.givenName;
      final family = apple.familyName;

      if ((given ?? family) != null && (result.user?.displayName ?? '').isEmpty) {
        await result.user?.updateDisplayName([given, family].nonNulls.join(' '));
        await result.user?.reload();
      }

      return _idTokenFrom(result, refresh: true);
    } on SignInWithAppleAuthorizationException catch (error) {
      if (error.code == AuthorizationErrorCode.canceled) {
        throw const SignInFailure.cancelled();
      }

      throw SignInFailure('Apple sign-in did not complete. ${error.message}');
    } on FirebaseAuthException catch (error) {
      throw SignInFailure(_firebaseMessage(error));
    }
  }

  Future<String> withPassword({required String email, required String password}) async {
    try {
      return _idTokenFrom(
        await _auth.signInWithEmailAndPassword(email: email, password: password),
      );
    } on FirebaseAuthException catch (error) {
      throw SignInFailure(_firebaseMessage(error));
    }
  }

  Future<String> registerWithPassword({
    required String name,
    required String email,
    required String password,
  }) async {
    try {
      final result = await _auth.createUserWithEmailAndPassword(
        email: email,
        password: password,
      );

      await result.user?.updateDisplayName(name);
      await result.user?.sendEmailVerification();

      return _idTokenFrom(result, refresh: true);
    } on FirebaseAuthException catch (error) {
      throw SignInFailure(_firebaseMessage(error));
    }
  }

  Future<void> sendPasswordReset(String email) async {
    try {
      await _auth.sendPasswordResetEmail(email: email);
    } on FirebaseAuthException catch (error) {
      throw SignInFailure(_firebaseMessage(error));
    }
  }

  /// Ends the Firebase session. The server session is ended separately — they
  /// are two different things, and leaving either behind is a bug.
  Future<void> signOut() async {
    await _auth.signOut();

    try {
      await GoogleSignIn.instance.signOut();
    } catch (_) {
      // Never configured on this platform, or never used. Nothing to undo.
    }
  }

  Future<String> _idTokenFrom(UserCredential result, {bool refresh = false}) async {
    // Forced refresh after a profile change, so the token carries the name the
    // server is about to store rather than the one from a moment ago.
    final token = await result.user?.getIdToken(refresh);

    if (token == null || token.isEmpty) {
      throw const SignInFailure('Sign-in did not complete. Please try again.');
    }

    return token;
  }

  String _firebaseMessage(FirebaseAuthException error) => switch (error.code) {
        'invalid-credential' ||
        'wrong-password' ||
        'user-not-found' =>
          'These details do not match an account.',
        'email-already-in-use' =>
          'That email address already has an account. Try signing in instead.',
        'weak-password' => 'Please choose a longer password.',
        'invalid-email' => 'That does not look like an email address.',
        'user-disabled' => 'This account has been disabled.',
        'too-many-requests' => 'Too many attempts. Please wait a moment.',
        'network-request-failed' =>
          'Cannot reach the sign-in service. Check your connection.',
        'account-exists-with-different-credential' =>
          'This email is already registered another way. Sign in the way you '
              'did before.',
        _ => 'Sign-in failed. Please try again.',
      };

  /// Google's own failures, which arrive as codes rather than sentences.
  String _readable(String code, String? description) {
    // The one that will happen in a fresh project: no OAuth client exists for
    // this app yet, because the signing fingerprint has not been registered.
    if (code.contains('configuration') || (description ?? '').contains('10')) {
      return 'Google sign-in is not configured for this build yet.';
    }

    return 'Google sign-in did not complete. Please try again.';
  }
}
