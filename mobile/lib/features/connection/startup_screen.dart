import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/errors/api_exception.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/app_logo.dart';

/// What the app does before it knows anything.
///
/// It verifies the stored token against the server, because a token on the
/// device is not proof of a session — it may have been revoked or the account
/// suspended. A failure here is shown as a connection problem, not as a
/// sign-out: losing somebody's session because their train went into a tunnel
/// would be its own bug.
class StartupScreen extends ConsumerStatefulWidget {
  const StartupScreen({super.key});

  @override
  ConsumerState<StartupScreen> createState() => _StartupScreenState();
}

class _StartupScreenState extends ConsumerState<StartupScreen> {
  String? _error;
  bool _busy = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _restore());
  }

  Future<void> _restore() async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      await ref.read(authProvider.notifier).restore();
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
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const AppLogo(size: 72),
              const SizedBox(height: 24),
              Text('My Generation', style: theme.textTheme.headlineMedium),
              const SizedBox(height: 8),
              Text(
                'Family tree · Genealogy · Heritage',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 40),
              if (_busy)
                const CircularProgressIndicator()
              else if (_error != null) ...[
                Icon(Icons.cloud_off_outlined, size: 40, color: theme.colorScheme.error),
                const SizedBox(height: 12),
                Text(_error!, textAlign: TextAlign.center, style: theme.textTheme.bodyLarge),
                const SizedBox(height: 24),
                FilledButton(onPressed: _restore, child: const Text('Try again')),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
