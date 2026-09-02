import 'package:flutter/material.dart';

/// Three linked generations, drawn rather than shipped as an asset.
///
/// A placeholder mark: it says what the app is about without pretending to be
/// finished branding.
class AppLogo extends StatelessWidget {
  const AppLogo({super.key, this.size = 48});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(painter: _LogoPainter(Theme.of(context).colorScheme.primary)),
    );
  }
}

class _LogoPainter extends CustomPainter {
  const _LogoPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final stroke = Paint()
      ..color = color
      ..strokeWidth = size.width * 0.045
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final fill = Paint()..color = color;
    final r = size.width * 0.085;

    final top = Offset(size.width * 0.5, size.height * 0.16);
    final midLeft = Offset(size.width * 0.26, size.height * 0.52);
    final midRight = Offset(size.width * 0.74, size.height * 0.52);
    final bottomLeft = Offset(size.width * 0.14, size.height * 0.86);
    final bottomRight = Offset(size.width * 0.86, size.height * 0.86);

    // The couple bar, then the drop lines — the same shape the tree draws.
    canvas.drawLine(top, Offset(top.dx, size.height * 0.34), stroke);
    canvas.drawLine(
      Offset(midLeft.dx, size.height * 0.34),
      Offset(midRight.dx, size.height * 0.34),
      stroke,
    );
    canvas.drawLine(midLeft, Offset(midLeft.dx, size.height * 0.34), stroke);
    canvas.drawLine(midRight, Offset(midRight.dx, size.height * 0.34), stroke);
    canvas.drawLine(midLeft, bottomLeft, stroke);
    canvas.drawLine(midRight, bottomRight, stroke);

    for (final point in [top, midLeft, midRight, bottomLeft, bottomRight]) {
      canvas.drawCircle(point, r, fill);
    }
  }

  @override
  bool shouldRepaint(_LogoPainter oldDelegate) => oldDelegate.color != color;
}
