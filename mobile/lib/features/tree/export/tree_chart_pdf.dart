import 'dart:io';
import 'dart:math' as math;
import 'dart:ui' show Offset, Rect;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart' show Color, ColorScheme;
import 'package:path_provider/path_provider.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:share_plus/share_plus.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/person_summary.dart';
import '../../../models/tree_graph.dart';
import '../../../models/tree_summary.dart';
import '../layout/tree_layout.dart';
import 'chart_caption.dart';
import 'pdf_text.dart';

/// The chart as a document rather than a photograph.
///
/// Drawn, not photographed: the connectors are lines and the names are text,
/// so the file stays sharp however far it is zoomed, prints at whatever size
/// the paper is, and can be searched for a name. A picture of the same chart
/// is tens of megabytes and goes soft the moment somebody enlarges it to read
/// a great-grandmother's dates.
///
/// It draws from the same [TreeLayout] the screen draws from, so a person
/// cannot sit in one place on the chart and another in the document.
///
/// What it leaves out is photographs: a face on a card here is drawn as
/// initials. Embedding them would mean fetching every one at export time, and
/// a document that takes a minute and fails on a slow connection is worth less
/// than one that is instant and says who everybody is.
class TreeChartPdf {
  const TreeChartPdf({
    required this.graph,
    required this.layout,
    required this.title,
    this.summary,
  });

  final TreeGraph graph;
  final TreeLayout layout;
  final String title;
  final TreeSummary? summary;

  static const double margin = 36;

  /// A PDF page may not exceed 14400 units on a side. A wide generation can
  /// pass that on its own, so the whole drawing is scaled to fit rather than
  /// written out at a size no reader will open.
  static const double maxSide = 14200;

  /// The document, so what it contains can be checked without a share sheet.
  Future<Uint8List> bytes() async {
    final scheme = AppTheme.light().colorScheme;
    final lines = ChartCaption.lines(graph: graph, summary: summary);

    final chart = layout.canvasSize;
    final captionHeight = 26 + lines.length * 12 + 12;

    final scale = math.min(
      1.0,
      maxSide /
          math.max(
            chart.width + margin * 2,
            chart.height + captionHeight + margin * 2,
          ),
    );

    final document = pw.Document(title: plainForPdf(title));

    document.addPage(
      pw.Page(
        pageFormat: PdfPageFormat(
          (chart.width + margin * 2) * scale,
          (chart.height + captionHeight + margin * 2) * scale,
          marginAll: margin * scale,
        ),
        build: (context) => pw.Stack(
          children: [
            // Sized rather than positioned, so the stack takes the size of the
            // chart instead of collapsing around children that all float.
            pw.SizedBox(
              width: chart.width * scale,
              height: (chart.height + captionHeight) * scale,
              child: pw.Stack(
                children: [
                  pw.CustomPaint(
                    size: PdfPoint(
                      chart.width * scale,
                      (chart.height + captionHeight) * scale,
                    ),
                    painter: (canvas, size) =>
                        _connectors(canvas, size, scale, scheme),
                  ),
                  for (final node in layout.nodes.values)
                    if (graph.person(node.ulid) case final person?)
                      pw.Positioned(
                        left: node.rect.left * scale,
                        top: (node.rect.top + captionHeight) * scale,
                        child: _card(
                          person: person,
                          rect: node.rect,
                          scale: scale,
                          scheme: scheme,
                          isFocus: node.ulid == graph.focusUlid,
                        ),
                      ),
                ],
              ),
            ),
            pw.Positioned(
              left: 0,
              top: 0,
              child: _caption(lines, scale, chart.width * scale),
            ),
          ],
        ),
      ),
    );

    return document.save();
  }

  /// Writes the document and hands it to the share sheet, returning how large
  /// it turned out — the caller says so, because a document that saved and a
  /// document that saved as nothing look the same from the outside.
  Future<int> share() async {
    final document = await bytes();
    final directory = await getTemporaryDirectory();
    final file = File('${directory.path}/${fileName(title)}');

    await file.writeAsBytes(document, flush: true);

    await SharePlus.instance.share(
      ShareParams(files: [XFile(file.path, mimeType: 'application/pdf')]),
    );

    return document.length;
  }

  pw.Widget _caption(List<CaptionLine> lines, double scale, double width) =>
      pw.SizedBox(
        width: width,
        child: pw.Column(
          crossAxisAlignment: pw.CrossAxisAlignment.start,
          children: [
            pw.Text(
              plainForPdf(title),
              style: pw.TextStyle(
                fontSize: 15 * scale,
                fontWeight: pw.FontWeight.bold,
              ),
            ),
            pw.SizedBox(height: 3 * scale),
            for (final line in lines)
              pw.Text(
                plainForPdf(line.text),
                style: pw.TextStyle(
                  fontSize: 8 * scale,
                  color: line.emphasis ? PdfColors.black : PdfColors.grey700,
                  fontWeight: line.emphasis
                      ? pw.FontWeight.bold
                      : pw.FontWeight.normal,
                ),
              ),
          ],
        ),
      );

  /// Every connector the painter draws on screen, in the same order.
  void _connectors(
    PdfGraphics canvas,
    PdfPoint size,
    double scale,
    ColorScheme scheme,
  ) {
    final line = _pdf(scheme.outline);
    final accent = _pdf(scheme.primary);

    // The painter works in screen coordinates, where y grows downwards; a PDF
    // page grows upwards from the bottom. Everything goes through here, so the
    // chart cannot come out mirrored in one place and upright in another.
    double y(double value) => size.y - value * scale;
    double x(double value) => value * scale;

    canvas.setLineCap(PdfLineCap.round);

    void stroke(
      Offset from,
      Offset to, {
      required bool dashed,
      required bool focus,
    }) {
      canvas
        ..setStrokeColor(focus ? accent : line)
        ..setLineWidth((focus ? 2.2 : 1.6) * scale)
        ..setLineDashPattern(dashed ? [6 * scale, 5 * scale] : const [])
        ..drawLine(x(from.dx), y(from.dy), x(to.dx), y(to.dy))
        ..strokePath();
    }

    for (final shape in layout.unionShapes) {
      final focus = shape.childDrops.any(
        (drop) => drop.childUlid == graph.focusUlid,
      );

      if (shape.partnerBar case final bar?) {
        stroke(bar.topLeft, bar.topRight, dashed: false, focus: focus);
      }

      if (shape.childDrops.isEmpty) continue;

      final barY = shape.childDrops.first.from.dy;

      stroke(
        shape.junction,
        Offset(shape.junction.dx, barY),
        dashed: false,
        focus: focus,
      );

      if (shape.siblingBar case final bar?) {
        stroke(bar.topLeft, bar.topRight, dashed: false, focus: focus);
      }

      for (final drop in shape.childDrops) {
        stroke(
          drop.from,
          drop.to,
          dashed: shape.dashedChildUlids.contains(drop.childUlid),
          focus: focus,
        );
      }
    }

    for (final edge in layout.looseEdges) {
      _elbow(canvas, edge, x, y, scale, line, dashed: edge.dashed);
    }
  }

  /// A parent-child link with no union behind it, with the same rounded
  /// corners the screen draws: right angles read as structure, diagonals read
  /// as arbitrary.
  void _elbow(
    PdfGraphics canvas,
    LooseEdge edge,
    double Function(double) x,
    double Function(double) y,
    double scale,
    PdfColor colour, {
    required bool dashed,
  }) {
    const radius = 10.0;
    final midY = (edge.from.dy + edge.to.dy) / 2;

    canvas
      ..setStrokeColor(colour)
      ..setLineWidth(1.6 * scale)
      ..setLineDashPattern(dashed ? [6 * scale, 5 * scale] : const [])
      ..moveTo(x(edge.from.dx), y(edge.from.dy));

    // Tracked here rather than asked of the canvas: a curve needs the point
    // the pen is already at, and the page has no way to tell us.
    var pen = edge.from;

    void lineTo(Offset to) {
      canvas.lineTo(x(to.dx), y(to.dy));
      pen = to;
    }

    /// PDF has no quadratic curve, so the control point is raised to the cubic
    /// that draws the identical arc.
    void curveTo(Offset control, Offset end) {
      final c1 = pen + (control - pen) * (2 / 3);
      final c2 = end + (control - end) * (2 / 3);

      canvas.curveTo(
        x(c1.dx),
        y(c1.dy),
        x(c2.dx),
        y(c2.dy),
        x(end.dx),
        y(end.dy),
      );
      pen = end;
    }

    if ((edge.from.dx - edge.to.dx).abs() < 1) {
      lineTo(edge.to);
    } else {
      final sweep = edge.to.dx > edge.from.dx ? radius : -radius;

      lineTo(Offset(edge.from.dx, midY - radius));
      curveTo(Offset(edge.from.dx, midY), Offset(edge.from.dx + sweep, midY));
      lineTo(Offset(edge.to.dx - sweep, midY));
      curveTo(Offset(edge.to.dx, midY), Offset(edge.to.dx, midY + radius));
      lineTo(edge.to);
    }

    canvas.strokePath();
  }

  pw.Widget _card({
    required PersonSummary person,
    required Rect rect,
    required double scale,
    required ColorScheme scheme,
    required bool isFocus,
  }) {
    final width = rect.width * scale;
    final height = rect.height * scale;

    if (person.placeholder) {
      return pw.Container(
        width: width,
        height: height,
        alignment: pw.Alignment.center,
        decoration: pw.BoxDecoration(
          color: PdfColors.grey200,
          borderRadius: pw.BorderRadius.circular(8 * scale),
          border: pw.Border.all(color: PdfColors.grey400, width: 0.7 * scale),
        ),
        child: pw.Text(
          'Private',
          style: pw.TextStyle(fontSize: 7 * scale, color: PdfColors.grey700),
        ),
      );
    }

    return pw.Container(
      width: width,
      height: height,
      padding: pw.EdgeInsets.symmetric(
        horizontal: 5 * scale,
        vertical: 4 * scale,
      ),
      decoration: pw.BoxDecoration(
        color: isFocus ? _pdf(scheme.primaryContainer) : PdfColors.white,
        borderRadius: pw.BorderRadius.circular(8 * scale),
        border: pw.Border.all(
          color: isFocus ? _pdf(scheme.primary) : PdfColors.grey400,
          width: (isFocus ? 1.6 : 0.7) * scale,
        ),
      ),
      child: pw.Column(
        mainAxisAlignment: pw.MainAxisAlignment.center,
        children: [
          pw.Container(
            width: 18 * scale,
            height: 18 * scale,
            alignment: pw.Alignment.center,
            decoration: const pw.BoxDecoration(
              color: PdfColors.grey200,
              shape: pw.BoxShape.circle,
            ),
            child: pw.Text(
              plainForPdf(initials(person.displayName)),
              style: pw.TextStyle(fontSize: 6.5 * scale),
            ),
          ),
          pw.SizedBox(height: 3 * scale),
          pw.Expanded(
            child: pw.Text(
              plainForPdf(person.displayName),
              textAlign: pw.TextAlign.center,
              maxLines: 2,
              overflow: pw.TextOverflow.clip,
              style: pw.TextStyle(
                fontSize: 7.5 * scale,
                fontWeight: pw.FontWeight.bold,
              ),
            ),
          ),
          pw.Text(
            plainForPdf(
              person.lifespan ?? (person.redacted ? 'Dates withheld' : '-'),
            ),
            maxLines: 1,
            style: pw.TextStyle(
              fontSize: 6.5 * scale,
              color: PdfColors.grey700,
            ),
          ),
        ],
      ),
    );
  }

  /// The same two letters the card shows on screen.
  static String initials(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .toList();

    if (parts.isEmpty) return '?';
    if (parts.length == 1) {
      return parts.first.substring(0, 1).toUpperCase();
    }

    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }

  static PdfColor _pdf(Color colour) => PdfColor.fromInt(colour.toARGB32());

  static String fileName(String title) {
    final stem = title
        .replaceAll(RegExp(r'[^A-Za-z0-9 ]'), '')
        .trim()
        .replaceAll(RegExp(r'\s+'), '-')
        .toLowerCase();

    final today = DateTime.now();
    final stamp =
        '${today.year}-${today.month.toString().padLeft(2, '0')}-'
        '${today.day.toString().padLeft(2, '0')}';

    return '${stem.isEmpty ? 'family-tree' : stem}-$stamp.pdf';
  }
}
