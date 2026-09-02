import 'package:flutter/material.dart';

/// A tinted message above a form. Errors, notices and confirmations all use it,
/// so the same kind of information always looks the same.
class FormBanner extends StatelessWidget {
  const FormBanner({super.key, required this.message, required this.tone, this.icon});

  final String message;
  final Color tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: tone.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 20, color: tone),
            const SizedBox(width: 10),
          ],
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: tone),
            ),
          ),
        ],
      ),
    );
  }
}
