import 'dart:ui';

/// The measurements the chart is built from.
///
/// Cards are wide enough for a full name and a lifespan at the app's larger
/// type size, and the vertical gap leaves room for a generation label between
/// rows without the connectors crowding it.
class TreeMetrics {
  const TreeMetrics({
    this.cardWidth = 148,
    this.cardHeight = 96,
    this.horizontalGap = 44,
    this.verticalGap = 76,
    this.partnerGap = 12,
    this.siblingBarOffset = 34,
  });

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
