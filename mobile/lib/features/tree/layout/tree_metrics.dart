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

  /// Sized for the type the device is actually rendering.
  ///
  /// The card is a fixed box on a laid-out canvas, so text that does not fit
  /// is clipped rather than wrapped — and the height was a constant guessed
  /// against one platform's font. Roboto squeaked in; SF Pro did not, so on
  /// iOS every card lost the bottom of its lifespan. Any accessibility text
  /// scaling broke both.
  ///
  /// Only the two text lines scale. The avatar, the gaps and the padding are
  /// fixed, so scaling the whole box would leave a card of mostly whitespace
  /// at large type sizes.
  factory TreeMetrics.forTextScale(TextScaler scaler) {
    // Avatar (28), the gaps around it (8) and the vertical padding (16).
    const fixed = 52.0;

    // Two lines of name plus one of lifespan, at the app's own type sizes.
    const text = 50.0;

    return TreeMetrics(cardHeight: fixed + scaler.scale(text));
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
