import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/constants/api_paths.dart';
import '../../core/errors/api_exception.dart';
import '../../providers/app_providers.dart';
import '../../widgets/form_banner.dart';

/// Requesting a password reset.
///
/// The server answers identically whether or not the address has an account, so
/// this endpoint cannot be used to discover who is registered. The screen says
/// the same thing, rather than implying a match was found.
class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();

  bool _busy = false;
  String? _sent;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _busy = true;
      _error = null;
      _sent = null;
    });

    try {
      final envelope = await ref.read(apiClientProvider).post<Map<String, dynamic>>(
            ApiPaths.forgotPassword,
            body: {'email': _email.text.trim()},
            parse: (data) => (data as Map).cast<String, dynamic>(),
          );

      if (mounted) setState(() => _sent = envelope.data?['message'] as String?);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Reset your password')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'We will send a link to the address on your account.',
                      style: theme.textTheme.bodyLarge,
                    ),
                    const SizedBox(height: 24),

                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      enabled: _sent == null,
                      decoration: const InputDecoration(labelText: 'Email address'),
                      validator: (value) =>
                          (value == null || value.trim().isEmpty) ? 'Enter your email address' : null,
                    ),

                    if (_sent != null) ...[
                      const SizedBox(height: 20),
                      FormBanner(
                        message: _sent!,
                        tone: theme.colorScheme.primary,
                        icon: Icons.mark_email_read_outlined,
                      ),
                      const SizedBox(height: 20),
                      FilledButton(
                        onPressed: () => Navigator.of(context).pop(),
                        child: const Text('Back to sign in'),
                      ),
                    ] else ...[
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
                            : const Text('Send reset link'),
                      ),
                    ],
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
