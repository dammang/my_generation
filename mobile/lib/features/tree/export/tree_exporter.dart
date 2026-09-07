import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image/image.dart' as img;
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../../models/tree_graph.dart';
import '../layout/tree_layout.dart';
import 'tree_export_image.dart';
import 'tree_export_view.dart';

/// What came of an export, in the terms somebody would ask about it.
class ExportedTree {
  const ExportedTree({
    required this.path,
    required this.pixels,
    required this.bytes,
  });

  final String path;
  final Size pixels;
  final int bytes;

  /// "5,904 × 3,180 · 3.1 MB"
  String get describe {
    final megabytes = (bytes / 1000000).toStringAsFixed(1);

    return '${pixels.width.round()} × ${pixels.height.round()} · $megabytes MB';
  }
}

/// Turns the chart into a photograph.
class TreeExporter {
  const TreeExporter();

  /// Very high quality. The chart is line work and small text, which is what
  /// JPEG is worst at, so the quality is set near the top of the scale where
  /// the ringing around a letter stops being visible.
  static const int quality = 95;

  Future<ExportedTree> export({
    required BuildContext context,
    required TreeGraph graph,
    required TreeLayout layout,
    required String title,
  }) async {
    final view = TreeExportView(graph: graph, layout: layout, title: title);
    final scale = ExportScale.forCanvas(view.size);

    // Photographs are drawn from the shared image cache, and an offscreen
    // pipeline cannot wait for a download mid-paint. Anything not already
    // fetched would leave an empty grey circle where a face should be.
    await _warmPhotographs(context, graph);

    if (!context.mounted) {
      throw StateError('The screen went away before the chart was drawn.');
    }

    final image = await renderOffscreen(
      widget: view,
      size: view.size,
      pixelRatio: scale.pixelRatio,
      view: View.of(context),
    );

    final raw = await image.toByteData(format: ui.ImageByteFormat.rawRgba);
    final pixels = Size(image.width.toDouble(), image.height.toDouble());

    image.dispose();

    if (raw == null) {
      throw StateError('The chart could not be read back as an image.');
    }

    // Encoded off the main thread: twenty-four megapixels takes seconds, and
    // on the UI thread that is an app somebody thinks has died.
    final jpeg = await compute(_encode, (
      bytes: raw.buffer.asUint8List(),
      width: image.width,
      height: image.height,
    ));

    final file = await _write(jpeg, title);

    return ExportedTree(path: file, pixels: pixels, bytes: jpeg.lengthInBytes);
  }

  Future<void> share(ExportedTree export, String title) =>
      SharePlus.instance.share(
        ShareParams(
          files: [XFile(export.path, mimeType: 'image/jpeg')],
          fileNameOverrides: [_fileName(title)],
        ),
      );

  Future<void> _warmPhotographs(BuildContext context, TreeGraph graph) async {
    final urls = graph.people.values
        .map((person) => person.photoUrl)
        .whereType<String>()
        .toSet();

    if (urls.isEmpty || !context.mounted) return;

    await Future.wait(
      urls.map(
        (url) => precacheImage(NetworkImage(url), context)
            // One slow photograph must not hold up the whole export; the card
            // falls back to initials, which is what it does anyway.
            .timeout(const Duration(seconds: 8), onTimeout: () {})
            .catchError((_) {}),
      ),
    );
  }

  Future<String> _write(Uint8List jpeg, String title) async {
    final directory = await getTemporaryDirectory();
    final file = File('${directory.path}/${_fileName(title)}');

    await file.writeAsBytes(jpeg, flush: true);

    return file.path;
  }

  static String _fileName(String title) {
    final stem = title
        .replaceAll(RegExp(r'[^A-Za-z0-9 ]'), '')
        .trim()
        .replaceAll(RegExp(r'\s+'), '-')
        .toLowerCase();

    final today = DateTime.now();
    final stamp =
        '${today.year}-${today.month.toString().padLeft(2, '0')}-'
        '${today.day.toString().padLeft(2, '0')}';

    return '${stem.isEmpty ? 'family-tree' : stem}-$stamp.jpg';
  }
}

/// Runs in an isolate, so it may not touch anything from the app.
Uint8List _encode(({Uint8List bytes, int width, int height}) frame) {
  final image = img.Image.fromBytes(
    width: frame.width,
    height: frame.height,
    bytes: frame.bytes.buffer,
    numChannels: 4,
    order: img.ChannelOrder.rgba,
  );

  return img.encodeJpg(image, quality: TreeExporter.quality);
}
