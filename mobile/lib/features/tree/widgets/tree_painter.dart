import 'package:flutter/material.dart';

import '../layout/tree_layout.dart';

/// Draws the connectors, and nothing else.
///
/// Cards are real widgets positioned over this canvas rather than painted into
/// it — a painted card cannot be tapped, focused, read by a screen reader, or
/// animated, and a family tree whose people are inert pictures is a diagram
/// rather than an interface.
class TreePainter extends CustomPainter {
  const TreePainter({
    required this.layout,
    required this.lineColour,
    required this.accentColour,
    required this.focusUlid,
  });

  final TreeLayout layout;
  final Color lineColour;

  /// Used for the lines touching the person at the centre, so the eye can find
  /// them again after panning.
  final Color accentColour;
  final String focusUlid;

  static const double _strokeWidth = 1.6;
  static const double _cornerRadius = 10;

  @override
  void paint(Canvas canvas, Size size) {
    final solid = Paint()
      ..color = lineColour
      ..strokeWidth = _strokeWidth
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    for (final shape in layout.unionShapes) {
      final touchesFocus = shape.childDrops.any((d) => d.childUlid == focusUlid);
      final paint = touchesFocus
          ? (Paint()
            ..color = accentColour
            ..strokeWidth = _strokeWidth + 0.6
            ..style = PaintingStyle.stroke
            ..strokeCap = StrokeCap.round)
          : solid;

      // The bar joining two partners.
      if (shape.partnerBar case final bar?) {
        canvas.drawLine(bar.topLeft, bar.topRight, paint);
      }

      if (shape.childDrops.isEmpty) continue;

      final barY = shape.childDrops.first.from.dy;

      // One line down from the couple to the sibling bar.
      canvas.drawLine(shape.junction, Offset(shape.junction.dx, barY), paint);

      if (shape.siblingBar case final bar?) {
        canvas.drawLine(bar.topLeft, bar.topRight, paint);
      }

      for (final drop in shape.childDrops) {
        final dashed = shape.dashedChildUlids.contains(drop.childUlid);
        _drawDrop(canvas, drop.from, drop.to, paint, dashed: dashed);
      }
    }

    for (final edge in layout.looseEdges) {
      _drawElbow(canvas, edge.from, edge.to, solid, dashed: edge.dashed);
    }
  }

  void _drawDrop(Canvas canvas, Offset from, Offset to, Paint paint, {required bool dashed}) {
    if (dashed) {
      _drawDashedLine(canvas, from, to, paint);
    } else {
      canvas.drawLine(from, to, paint);
    }
  }

  /// An orthogonal connector with rounded corners, for links with no union
  /// behind them. Diagonals read as arbitrary; right angles read as structure.
  void _drawElbow(Canvas canvas, Offset from, Offset to, Paint paint, {required bool dashed}) {
    final midY = (from.dy + to.dy) / 2;
    final path = Path()..moveTo(from.dx, from.dy);

    if ((from.dx - to.dx).abs() < 1) {
      path.lineTo(to.dx, to.dy);
    } else {
      final sweep = to.dx > from.dx ? _cornerRadius : -_cornerRadius;

      path
        ..lineTo(from.dx, midY - _cornerRadius)
        ..quadraticBezierTo(from.dx, midY, from.dx + sweep, midY)
        ..lineTo(to.dx - sweep, midY)
        ..quadraticBezierTo(to.dx, midY, to.dx, midY + _cornerRadius)
        ..lineTo(to.dx, to.dy);
    }

    if (dashed) {
      canvas.drawPath(_dashPath(path), paint);
    } else {
      canvas.drawPath(path, paint);
    }
  }

  void _drawDashedLine(Canvas canvas, Offset from, Offset to, Paint paint) {
    canvas.drawPath(_dashPath(Path()..moveTo(from.dx, from.dy)..lineTo(to.dx, to.dy)), paint);
  }

  /// Adoptive and step links are drawn dashed. Not decoration: the chart should
  /// not silently assert biology it has no evidence for.
  static Path _dashPath(Path source, {double dash = 6, double gap = 5}) {
    final dashed = Path();

    for (final metric in source.computeMetrics()) {
      var distance = 0.0;

      while (distance < metric.length) {
        final end = (distance + dash).clamp(0.0, metric.length);
        dashed.addPath(metric.extractPath(distance, end), Offset.zero);
        distance = end + gap;
      }
    }

    return dashed;
  }

  @override
  bool shouldRepaint(TreePainter oldDelegate) =>
      oldDelegate.layout != layout ||
      oldDelegate.lineColour != lineColour ||
      oldDelegate.focusUlid != focusUlid;
}
