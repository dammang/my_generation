import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/api_exception.dart';
import '../../../providers/auth_provider.dart';

/// Offers a way out of an unconfirmed address.
///
/// The server refuses contributions from an unconfirmed address and says to
/// ask for a new link. Saying that without offering anywhere to do it is worse
/// than not saying it: somebody blocked from adding their own grandmother
/// should not have to go looking for the fix.
///
/// Shown above the screen rather than only on the refusal, so the first time
/// somebody meets it is not halfway through losing a piece of work.
class VerifyEmailBanner extends ConsumerStatefulWidget {
  const VerifyEmailBanner({super.key});

  @override
  ConsumerState<VerifyEmailBanner> createState() => _VerifyEmailBannerState();
}

class _VerifyEmailBannerState extends ConsumerState<VerifyEmailBanner> {
  bool _busy = false;
  String? _note;

  Future<void> _resend() async {
    setState(() {
      _busy = true;
      _note = null;
    });

    try {
      await ref.read(authProvider.notifier).resendVerificationEmail();
      if (mounted) {
        setState(() => _note = 'Sent. Check your inbox, and your spam folder.');
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          // Asking twice in a minute is throttled, which is not a failure
          // worth alarming anybody about.
          _note = error.isThrottled
              ? 'A link was just sent. Give it a minute before asking again.'
              : error.message;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);

    if (auth is! AuthSignedIn || auth.user.emailVerified) {
      return const SizedBox.shrink();
    }

    final theme = Theme.of(context);
    final tone = theme.colorScheme.tertiary;

    return Material(
      color: tone.withValues(alpha: 0.10),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.mark_email_unread_outlined, size: 18, color: tone),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Confirm ${auth.user.email} to start contributing',
                    style: theme.textTheme.bodyMedium,
                  ),
                  if (_note != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      _note!,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 8),
            if (_busy)
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                child: SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              )
            else
              TextButton(onPressed: _resend, child: const Text('Resend')),
          ],
        ),
      ),
    );
  }
}
