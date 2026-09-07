import 'package:flutter/painting.dart';

/// The measurements the chart is built from.
///
/// Cards are wide enough for a full name and a lifespan at the app's larger
/// type size, and the vertical gap leaves room for a generation label between
/// rows without the connectors crowding it.
class TreeMetrics {
  const TreeMetrics({
    this.cardWidth = 160,
    this.cardHeight = 106,
    this.horizontalGap = 44,
    this.verticalGap = 76,
    this.partnerGap = 12,
    this.siblingBarOffset = 34,
  });

  /// Sized by measuring the type the device is actually about to draw.
  ///
  /// The card is a fixed box on a laid-out canvas, so anything that does not
  /// fit is clipped rather than wrapped. The height used to be a constant
  /// guessed against one platform's font, then a constant multiplied by the
  /// text scale — and it was four to six points short at every size, on every
  /// name. The name is Flexible, so it absorbed the shortfall silently by
  /// giving up the bottom of its second line: every surname on the chart was
  /// cut through the middle of its descenders.
  ///
  /// Two lines of name and one of dates, measured. No guess, no margin: the
  /// same styles the card renders with, laid out by the same engine, at the
  /// same scale.
  ///
  /// Only the text is measured. The avatar, the gaps and the padding are
  /// fixed, so scaling the whole box would leave a card of mostly whitespace
  /// at large type sizes.
  factory TreeMetrics.forText({
    required TextScaler scaler,
    TextStyle? name,
    TextStyle? dates,
  }) {
    // Avatar (28), the gaps around it (8), the vertical padding (16) and the
    // border, which a Container draws outside its padding (2).
    const chrome = 54.0;

    // Two lines laid out as two lines, not one line counted twice: the leading
    // between them belongs to the block and was missing from the reservation.
    final nameBlock = _textHeight(
      name,
      scaler,
      lines: 2,
      height: nameLineHeight,
    );
    final dateBlock = _textHeight(
      dates,
      scaler,
      lines: 1,
      height: dateLineHeight,
    );

    return TreeMetrics(cardHeight: chrome + nameBlock + dateBlock);
  }

  /// The line heights the card applies, and therefore the ones to measure.
  ///
  /// Set explicitly on both texts rather than left to the style, because a
  /// Text merges its style onto whatever DefaultTextStyle it happens to be
  /// under — so the dates were drawn at the ambient leading while the
  /// reservation was measured at the font's own. Four points, every card.
  static const double nameLineHeight = 1.15;
  static const double dateLineHeight = 1.3;

  /// What this many lines of this style actually occupy on this device.
  ///
  /// Measured with the same TextPainter the card will use, because the only
  /// number that matters is the one the font produces — and it differs between
  /// Roboto and SF Pro by more than the shortfall that was clipping names.
  static double _textHeight(
    TextStyle? style,
    TextScaler scaler, {
    required int lines,
    double? height,
  }) {
    final painter = TextPainter(
      text: TextSpan(
        // Ascender and descender on every line, so the measurement covers the
        // part of a letter that was being cut off.
        text: List.filled(lines, 'Ag').join('\n'),
        style: (style ?? const TextStyle(fontSize: 14)).copyWith(
          height: height,
        ),
      ),
      textDirection: TextDirection.ltr,
      textScaler: scaler,
      maxLines: lines,
    )..layout();

    return painter.height;
  }

  final double cardWidth;
  final double cardHeight;

  /// Between neighbours in the same row.
  final double horizontalGap;

  /// Between rows — the space the connectors live in.
  final double verticalGap;

  /// Between two partners, narrower than the sibling gap so a couple reads as
  /// a pair rather than as two unrelated neighbours.
  final double partnerGap;

  /// How far below the parents the sibling bar sits.
  final double siblingBarOffset;

  double get rowPitch => cardHeight + verticalGap;

  double get slot => cardWidth + horizontalGap;

  Size get cardSize => Size(cardWidth, cardHeight);
}
