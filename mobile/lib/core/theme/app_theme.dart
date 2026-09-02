import 'package:flutter/material.dart';

/// The visual language of the archive.
///
/// Two constraints shape every choice here. The people using this span
/// grandchildren and grandparents, so type is large and contrast is generous by
/// default rather than as an accessibility afterthought. And the subject is a
/// family's own history, so the palette is quiet — ink and paper, not a product
/// launch.
class AppTheme {
  const AppTheme._();

  /// Deep ink-green: the colour of a register kept in fountain pen.
  static const Color _seed = Color(0xFF1F4A3D);

  /// Reserved for verification and provenance, never for decoration.
  static const Color verified = Color(0xFF2C6B4C);
  static const Color disputed = Color(0xFFB4541F);
  static const Color redacted = Color(0xFF6B7280);

  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final scheme = ColorScheme.fromSeed(seedColor: _seed, brightness: brightness);
    final isLight = brightness == Brightness.light;

    return ThemeData(
      colorScheme: scheme,
      useMaterial3: true,
      // A faintly warm ground rather than pure white: long reading sessions on
      // a phone, often by older eyes.
      scaffoldBackgroundColor: isLight ? const Color(0xFFF7F8F5) : const Color(0xFF11150F),
      textTheme: _textTheme(brightness),
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 1,
        backgroundColor: isLight ? const Color(0xFFF7F8F5) : const Color(0xFF11150F),
        titleTextStyle: TextStyle(
          fontSize: 22,
          fontWeight: FontWeight.w600,
          color: scheme.onSurface,
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: scheme.outlineVariant),
        ),
        margin: EdgeInsets.zero,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isLight ? Colors.white : const Color(0xFF1A1F17),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          // A comfortable target for an unsteady hand.
          minimumSize: const Size.fromHeight(52),
          textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      dividerTheme: DividerThemeData(color: scheme.outlineVariant, space: 1, thickness: 1),
    );
  }

  /// Larger than Material's defaults throughout.
  ///
  /// Elderly relatives are the people most likely to hold the knowledge this
  /// archive exists to record, and the least likely to tolerate 12pt captions.
  static TextTheme _textTheme(Brightness brightness) {
    return const TextTheme(
      displaySmall: TextStyle(fontSize: 34, fontWeight: FontWeight.w600, height: 1.15),
      headlineMedium: TextStyle(fontSize: 26, fontWeight: FontWeight.w600, height: 1.2),
      headlineSmall: TextStyle(fontSize: 22, fontWeight: FontWeight.w600),
      titleLarge: TextStyle(fontSize: 20, fontWeight: FontWeight.w600),
      titleMedium: TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
      bodyLarge: TextStyle(fontSize: 17, height: 1.45),
      bodyMedium: TextStyle(fontSize: 15.5, height: 1.45),
      labelLarge: TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
      labelMedium: TextStyle(fontSize: 13.5, letterSpacing: 0.2),
    ).apply(
      bodyColor: brightness == Brightness.light ? const Color(0xFF17201D) : const Color(0xFFE6EAE5),
      displayColor: brightness == Brightness.light ? const Color(0xFF17201D) : const Color(0xFFE6EAE5),
    );
  }
}
