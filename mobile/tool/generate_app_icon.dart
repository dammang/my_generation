import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/widgets/app_logo.dart';

/// Draws the launcher icon from the same painter the app shows on startup.
///
/// Run it, then hand the result to tool/install_app_icon.sh:
///
///   flutter test tool/generate_app_icon.dart
///
/// A test rather than a script because painting needs the engine, and this is
/// the only way to get a real canvas without shipping a second copy of the
/// mark as an asset that nothing keeps in step with the first.
/// Pale on green rather than green on pale: a near-white icon reads as a gap
/// on a light home screen, next to apps that all hold their own square.
const _ground = Color(0xFF1F4A3D);
const _mark = Color(0xFFF7F8F5);

/// Apple applies its own rounded mask, so the art is inset rather than bled to
/// the edge; the maskable variant leaves the wider margin Android crops into.
Future<void> _write(
  String path,
  int side,
  double inset, {
  Color ground = _ground,
  Color mark = _mark,
}) async {
  final recorder = ui.PictureRecorder();
  final canvas = Canvas(recorder);
  final size = side.toDouble();

  canvas.drawRect(Rect.fromLTWH(0, 0, size, size), Paint()..color = ground);

  final art = size * inset;
  canvas.translate((size - art) / 2, (size - art) / 2);
  AppLogoPainter(mark).paint(canvas, Size(art, art));

  final image = await recorder.endRecording().toImage(side, side);
  final png = await image.toByteData(format: ui.ImageByteFormat.png);

  image.dispose();

  File(path).writeAsBytesSync(png!.buffer.asUint8List());
}

void main() {
  testWidgets('the icon is drawn from the mark the app shows', (tester) async {
    await tester.runAsync(() async {
      await _write('tool/app_icon.png', 1024, 0.86);
      await _write('tool/app_icon_maskable.png', 1024, 0.62);

      // The other way up, kept for comparison rather than shipped.
      await _write(
        'tool/app_icon_pale.png',
        1024,
        0.86,
        ground: _mark,
        mark: _ground,
      );
    });

    for (final name in ['app_icon', 'app_icon_maskable', 'app_icon_pale']) {
      expect(File('tool/$name.png').lengthSync(), greaterThan(1000));
    }
  });
}
