import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/errors/api_exception.dart';
import '../../providers/app_providers.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/app_logo.dart';
import '../../widgets/form_banner.dart';

/// Creating an account.
///
/// Registering never links the account to anybody in the archive — that only
/// happens through an approved claim. The copy says so, because a new user's
/// reasonable assumption is the opposite.
class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();

  bool _busy = false;
  bool _obscure = true;
  String? _error;
  Map<String, List<String>> _fieldErrors = const {};

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _busy = true;
      _error = null;
      _fieldErrors = const {};
    });

    try {
      final user = await ref.read(authRepositoryProvider).register(
            name: _name.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
          );

      ref.read(authProvider.notifier).adopt(user);
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _fieldErrors = error.errors;
          // Field-level messages are shown against their field; only a message
          // with nowhere else to go becomes a banner.
          _error = error.errors.isEmpty ? error.message : null;
        });
        _formKey.currentState?.validate();
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Create an account')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 8),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Center(child: AppLogo(size: 52)),
                    const SizedBox(height: 20),
                    Text(
                      'Your account is separate from the people in the archive. '
                      'Once you have joined a tribe you can ask to be recognised '
                      'as your own record.',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 24),

                    TextFormField(
                      controller: _name,
                      textCapitalization: TextCapitalization.words,
                      autofillHints: const [AutofillHints.name],
                      decoration: const InputDecoration(labelText: 'Your name'),
                      validator: (value) =>
                          (value == null || value.trim().isEmpty) ? 'Enter your name' : null,
                    ),
                    const SizedBox(height: 16),

                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      autofillHints: const [AutofillHints.email],
                      decoration: const InputDecoration(labelText: 'Email address'),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) return 'Enter your email address';
                        return _fieldErrors['email']?.first;
                      },
                    ),
                    const SizedBox(height: 16),

                    TextFormField(
                      controller: _password,
                      obscureText: _obscure,
                      autofillHints: const [AutofillHints.newPassword],
                      decoration: InputDecoration(
                        labelText: 'Password',
                        helperText: 'At least 8 characters, with letters and numbers',
                        helperMaxLines: 2,
                        suffixIcon: IconButton(
                          onPressed: () => setState(() => _obscure = !_obscure),
                          icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                          tooltip: _obscure ? 'Show password' : 'Hide password',
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) return 'Choose a password';
                        return _fieldErrors['password']?.first;
                      },
                    ),

                    if (_error != null) ...[
                      const SizedBox(height: 16),
                      FormBanner(message: _error!, tone: theme.colorScheme.error),
                    ],

                    const SizedBox(height: 28),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: _busy
                          ? const SizedBox(
                              height: 22, width: 22,
                              child: CircularProgressIndicator(strokeWidth: 2.4),
                            )
                          : const Text('Create account'),
                    ),
                    const SizedBox(height: 12),
                    TextButton(
                      onPressed: _busy ? null : () => Navigator.of(context).pop(),
                      child: const Text('I already have an account'),
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
