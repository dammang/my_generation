import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/errors/api_exception.dart';
import '../../models/joinable_scope.dart';
import '../../providers/onboarding_provider.dart';
import '../../widgets/form_banner.dart';

/// What a family asks at the door.
///
/// A request used to carry an account name and nothing else, which left
/// whoever reviews it deciding whether a stranger belongs to their family on
/// no evidence at all. Parents and grandparents are how a Zomi family places
/// somebody, so they are what is asked.
///
/// The grandparents may be left blank — not knowing them is the ordinary state
/// of an oral archive rather than an evasion — and so may the photograph, so
/// that an elder on a borrowed phone is not stopped by a camera.
class JoinClanFormScreen extends ConsumerStatefulWidget {
  const JoinClanFormScreen({super.key, required this.clan});

  final JoinableScope clan;

  @override
  ConsumerState<JoinClanFormScreen> createState() => _JoinClanFormScreenState();
}

class _JoinClanFormScreenState extends ConsumerState<JoinClanFormScreen> {
  final _form = GlobalKey<FormState>();
  final _fields = <String, TextEditingController>{
    'applicant_name': TextEditingController(),
    'father_name': TextEditingController(),
    'mother_name': TextEditingController(),
    'grandfather_name': TextEditingController(),
    'grandmother_name': TextEditingController(),
    'country': TextEditingController(),
    'contact': TextEditingController(),
  };

  XFile? _selfie;
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _takeSelfie() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.camera,
      preferredCameraDevice: CameraDevice.front,
      maxWidth: 1200,
      imageQuality: 85,
    );

    if (picked != null && mounted) setState(() => _selfie = picked);
  }

  Future<void> _chooseSelfie() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1200,
      imageQuality: 85,
    );

    if (picked != null && mounted) setState(() => _selfie = picked);
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;

    setState(() {
      _sending = true;
      _error = null;
    });

    final navigator = Navigator.of(context);
    final messenger = ScaffoldMessenger.of(context);

    try {
      // Read here rather than in the repository: on the web a picked file is
      // a blob with no path behind it, and reading it is the only way to get
      // at the bytes.
      final photo = _selfie;
      final bytes = photo == null ? null : await photo.readAsBytes();

      await ref
          .read(onboardingRepositoryProvider)
          .requestMembership(
            scopeType: widget.clan.type,
            scopeUlid: widget.clan.ulid,
            answers: {
              for (final entry in _fields.entries) entry.key: entry.value.text,
            },
            photoBytes: bytes,
            photoName: photo?.name,
          );

      ref.invalidate(myMembershipsProvider);
      ref.invalidate(needsOnboardingProvider);

      navigator.pop();
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            'Asked to join ${widget.clan.name}. '
            'Somebody in the family will review it.',
          ),
        ),
      );
    } catch (error) {
      // Everything, not just ApiException. A narrower catch let an unsupported
      // upload escape unhandled, and the button did nothing and said nothing —
      // which is worse than any error message.
      if (mounted) {
        setState(
          () => _error = error is ApiException
              ? error.message
              : 'Could not send the request. $error',
        );
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text('Join ${widget.clan.name}')),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 40),
          children: [
            Text(
              'So the family can place you. Whoever runs '
              '${widget.clan.name} reads this and nobody else.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 20),

            if (_error != null) ...[
              FormBanner(message: _error!, tone: theme.colorScheme.error),
              const SizedBox(height: 16),
            ],

            _text('applicant_name', 'Your name', required: true),
            _text('father_name', 'Father', required: true),
            _text('mother_name', 'Mother', required: true),
            _text('grandfather_name', 'Grandfather', hint: 'If you know it'),
            _text('grandmother_name', 'Grandmother', hint: 'If you know it'),
            _country(),
            _text(
              'contact',
              'Contact',
              required: true,
              hint: 'A phone number or email they can reach you on',
            ),

            const SizedBox(height: 8),
            _Selfie(
              picked: _selfie,
              onCamera: _takeSelfie,
              onGallery: _chooseSelfie,
              onClear: () => setState(() => _selfie = null),
            ),

            const SizedBox(height: 24),
            FilledButton(
              onPressed: _sending ? null : _submit,
              child: _sending
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2.2),
                    )
                  : const Text('Send request'),
            ),
            const SizedBox(height: 12),
            Text(
              'Until somebody approves you, you will only see what is public.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _text(
    String field,
    String label, {
    bool required = false,
    String? hint,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: TextFormField(
      controller: _fields[field],
      textCapitalization: TextCapitalization.words,
      decoration: InputDecoration(labelText: label, helperText: hint),
      validator: required
          ? (value) => (value == null || value.trim().isEmpty)
                ? 'Please give $label.'
                : null
          : null,
    ),
  );

  /// Country only. A family archive has no business holding the street
  /// address of somebody it has not let in yet.
  Widget _country() => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: TextFormField(
      controller: _fields['country'],
      textCapitalization: TextCapitalization.characters,
      maxLength: 2,
      decoration: const InputDecoration(
        labelText: 'Country',
        helperText: 'Two-letter code — MM, IN, US, MY',
        counterText: '',
      ),
      validator: (value) => (value == null || value.trim().length != 2)
          ? 'Give the country as its two-letter code.'
          : null,
    ),
  );
}

class _Selfie extends StatelessWidget {
  const _Selfie({
    required this.picked,
    required this.onCamera,
    required this.onGallery,
    required this.onClear,
  });

  final XFile? picked;
  final VoidCallback onCamera;
  final VoidCallback onGallery;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Photograph of you', style: theme.textTheme.labelLarge),
        const SizedBox(height: 4),
        Text(
          picked == null
              ? 'Optional, and it helps somebody recognise you. Only the '
                    'reviewer sees it — it never goes into the family album.'
              : 'Ready to send.',
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            OutlinedButton.icon(
              onPressed: onCamera,
              icon: const Icon(Icons.photo_camera_outlined),
              label: const Text('Camera'),
            ),
            const SizedBox(width: 10),
            OutlinedButton.icon(
              onPressed: onGallery,
              icon: const Icon(Icons.photo_library_outlined),
              label: const Text('Choose'),
            ),
            if (picked != null) ...[
              const SizedBox(width: 10),
              IconButton(
                onPressed: onClear,
                icon: const Icon(Icons.close),
                tooltip: 'Remove',
              ),
            ],
          ],
        ),
      ],
    );
  }
}
