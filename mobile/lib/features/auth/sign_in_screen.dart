import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:go_router/go_router.dart';

import '../../core/errors/api_exception.dart';
import '../../providers/app_providers.dart';
import '../../services/firebase_sign_in_service.dart';
import '../../routing/app_router.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/app_logo.dart';

/// Sign-in.
///
/// Deliberately plain at this stage: the foundation phase proves the app can
/// authenticate against the real API. Registration, password reset and the
/// onboarding that follows are the next phase's work.
class SignInScreen extends ConsumerStatefulWidget {
  const SignInScreen({super.key});

  @override
  ConsumerState<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends ConsumerState<SignInScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();

  bool _busy = false;
  bool _obscure = true;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref.read(authProvider.notifier).signInWithFirebasePassword(
            email: _email.text.trim(),
            password: _password.text,
          );
    } on SignInFailure catch (failure) {
      // Firebase answers a wrong password and an unknown address the same way,
      // so this cannot be used to discover who has an account.
      if (mounted && !failure.cancelled) {
        setState(() => _error = failure.message);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.errorFor('email') ?? error.message);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  /// Google and Apple. Both end in the same exchange as a password sign-in.
  Future<void> _withProvider(Future<void> Function() signIn) async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await signIn();
    } on SignInFailure catch (failure) {
      // Closing the sheet is not an error, and reporting it as one makes the
      // app look broken when nothing went wrong.
      if (mounted && !failure.cancelled) {
        setState(() => _error = failure.message);
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final signedOutMessage = switch (ref.watch(authProvider)) {
      AuthSignedOut(message: final m) => m,
      _ => null,
    };

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Center(child: AppLogo(size: 64)),
                    const SizedBox(height: 20),
                    Text('My Generation', style: theme.textTheme.headlineMedium, textAlign: TextAlign.center),
                    const SizedBox(height: 6),
                    Text(
                      'Sign in to your family archive',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 32),

                    if (signedOutMessage != null) ...[
                      _Banner(message: signedOutMessage, tone: theme.colorScheme.tertiary),
                      const SizedBox(height: 16),
                    ],

                    _ProviderButtons(
                      busy: _busy,
                      onGoogle: () => _withProvider(
                        ref.read(authProvider.notifier).signInWithGoogle,
                      ),
                      onApple: () => _withProvider(
                        ref.read(authProvider.notifier).signInWithApple,
                      ),
                      appleAvailable: ref.read(firebaseSignInProvider).appleAvailable,
                    ),
                    const _OrDivider(),

                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      autofillHints: const [AutofillHints.username],
                      decoration: const InputDecoration(labelText: 'Email address'),
                      validator: (value) =>
                          (value == null || value.trim().isEmpty) ? 'Enter your email address' : null,
                    ),
                    const SizedBox(height: 16),

                    TextFormField(
                      controller: _password,
                      obscureText: _obscure,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        labelText: 'Password',
                        suffixIcon: IconButton(
                          onPressed: () => setState(() => _obscure = !_obscure),
                          icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                          tooltip: _obscure ? 'Show password' : 'Hide password',
                        ),
                      ),
                      validator: (value) =>
                          (value == null || value.isEmpty) ? 'Enter your password' : null,
                    ),

                    if (_error != null) ...[
                      const SizedBox(height: 16),
                      _Banner(message: _error!, tone: theme.colorScheme.error),
                    ],

                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: _busy
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(strokeWidth: 2.4),
                            )
                          : const Text('Sign in'),
                    ),
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: _busy ? null : () => context.push(Routes.forgotPassword),
                      child: const Text('I forgot my password'),
                    ),
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 8),
                      child: Divider(),
                    ),
                    OutlinedButton(
                      onPressed: _busy ? null : () => context.push(Routes.register),
                      style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(52)),
                      child: const Text('Create an account'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({required this.message, required this.tone});

  final String message;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: tone.withValues(alpha: 0.35)),
      ),
      child: Text(
        message,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: tone),
      ),
    );
  }
}

/// Google and Apple, above the password fields.
///
/// Apple only appears on iOS. Offering it on Android would present a sign-in
/// that cannot complete, and Apple requires it alongside Google on iOS anyway.
class _ProviderButtons extends StatelessWidget {
  const _ProviderButtons({
    required this.busy,
    required this.onGoogle,
    required this.onApple,
    required this.appleAvailable,
  });

  final bool busy;
  final VoidCallback onGoogle;
  final VoidCallback onApple;
  final bool appleAvailable;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      children: [
        OutlinedButton.icon(
          onPressed: busy ? null : onGoogle,
          icon: const Icon(Icons.g_mobiledata, size: 28),
          label: const Text('Continue with Google'),
          style: OutlinedButton.styleFrom(
            minimumSize: const Size.fromHeight(52),
          ),
        ),
        if (appleAvailable) ...[
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: busy ? null : onApple,
            icon: const Icon(Icons.apple, size: 24),
            label: const Text('Continue with Apple'),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(52),
              foregroundColor: theme.colorScheme.onSurface,
            ),
          ),
        ],
      ],
    );
  }
}

class _OrDivider extends StatelessWidget {
  const _OrDivider();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 18),
      child: Row(
        children: [
          const Expanded(child: Divider()),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Text(
              'or use an email address',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
          const Expanded(child: Divider()),
        ],
      ),
    );
  }
}
