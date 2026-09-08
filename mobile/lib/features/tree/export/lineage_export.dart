import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image/image.dart' as img;
import 'package:path_provider/path_provider.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:share_plus/share_plus.dart';

import '../../../core/theme/app_theme.dart';
import '../../../models/person_summary.dart';
import 'pdf_text.dart';
import 'tree_export_image.dart';

/// One lineage, as something you can send somebody.
///
/// Two formats because they answer different questions. The document is for
/// reading and printing — real text, sharp at any size, and a thirty
/// generation line broken across pages. The picture is for sending to
/// somebody who will look at it on a phone and never print it.
///
/// Both are built from the same rows in the same order, so the two can never
/// disagree about a family.
class LineageExport {
  const LineageExport({
    required this.people,
    required this.title,
    required this.outerOrigin,
    required this.origin,
  });

  final List<PersonSummary> people;

  /// Whose lineage it is.
  final String title;

  /// Who each of the two columns counts from.
  final String? outerOrigin;
  final String? origin;

  Future<void> shareDocument() async =>
      _share(await _write(await documentBytes(), 'pdf'), 'application/pdf');

  /// The document itself, so what it contains can be checked directly.
  Future<Uint8List> documentBytes() async {
    final document = pw.Document(title: plainForPdf(title));

    document.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.all(36),
        header: (context) => context.pageNumber == 1
            ? pw.SizedBox()
            // Repeated on every page but the first, where the title is: a
            // sheet of numbers with no heading tells its reader nothing.
            : _headings(),
        build: (context) => [
          pw.Text(
            plainForPdf(title),
            style: pw.TextStyle(fontSize: 22, fontWeight: pw.FontWeight.bold),
          ),
          pw.SizedBox(height: 4),
          pw.Text(
            plainForPdf(_subtitle),
            style: const pw.TextStyle(fontSize: 10, color: PdfColors.grey700),
          ),
          pw.SizedBox(height: 14),
          _headings(),
          pw.Divider(height: 6),
          for (final (index, person) in people.indexed) _row(index, person),
        ],
      ),
    );

    return document.save();
  }

  Future<void> sharePicture(BuildContext context) async {
    final view = _PictureView(export: this);
    final scale = ExportScale.forCanvas(view.size);

    final image = await renderOffscreen(
      widget: view,
      size: view.size,
      pixelRatio: scale.pixelRatio,
      view: View.of(context),
    );

    final raw = await image.toByteData(format: ui.ImageByteFormat.rawRgba);
    final width = image.width;
    final height = image.height;

    image.dispose();

    if (raw == null) {
      throw StateError('The lineage could not be read back as an image.');
    }

    final jpeg = await compute(_encode, (
      bytes: raw.buffer.asUint8List(),
      width: width,
      height: height,
    ));

    await _share(await _write(jpeg, 'jpg'), 'image/jpeg');
  }

  pw.Widget _headings() => pw.Row(
    children: [
      pw.Expanded(flex: 3, child: _heading(_outerHeading)),
      pw.Expanded(
        flex: 3,
        child: _heading(origin == null ? '' : 'From $origin'),
      ),
      pw.Expanded(flex: 4, child: _heading('Name')),
    ],
  );

  pw.Widget _heading(String text) => pw.Text(
    plainForPdf(text),
    style: pw.TextStyle(
      fontSize: 9,
      fontWeight: pw.FontWeight.bold,
      color: PdfColors.grey800,
    ),
  );

  pw.Widget _row(int index, PersonSummary person) {
    final standing = person.generation;
    final last = index == people.length - 1;

    return pw.Container(
      padding: const pw.EdgeInsets.symmetric(vertical: 5),
      decoration: const pw.BoxDecoration(
        border: pw.Border(
          bottom: pw.BorderSide(color: PdfColors.grey300, width: 0.5),
        ),
      ),
      child: pw.Row(
        children: [
          pw.Expanded(
            flex: 3,
            child: pw.Text(
              _ordinalOrDash(standing?.outerNumber),
              style: const pw.TextStyle(fontSize: 11),
            ),
          ),
          pw.Expanded(
            flex: 3,
            child: pw.Text(
              _ordinalOrDash(standing?.number),
              style: const pw.TextStyle(fontSize: 11),
            ),
          ),
          pw.Expanded(
            flex: 4,
            child: pw.Text(
              // Where the line stops is the reason anybody printed it.
              plainForPdf(last ? '${person.displayName}  <-' : person.displayName),
              style: pw.TextStyle(
                fontSize: 11,
                fontWeight: last ? pw.FontWeight.bold : pw.FontWeight.normal,
              ),
            ),
          ),
        ],
      ),
    );
  }

  String get _outerHeading =>
      outerOrigin == null ? 'Generation' : 'From $outerOrigin';

  String get _subtitle {
    final ends = people.isEmpty ? null : people.last.displayName;

    return '${people.length} generations'
        '${ends == null ? '' : ', down to $ends'}';
  }

  Future<String> _write(Uint8List bytes, String extension) async {
    final directory = await getTemporaryDirectory();
    final file = File('${directory.path}/${_fileName(extension)}');

    await file.writeAsBytes(bytes, flush: true);

    return file.path;
  }

  Future<void> _share(String path, String mime) => SharePlus.instance.share(
    ShareParams(files: [XFile(path, mimeType: mime)]),
  );

  String _fileName(String extension) {
    final stem = title
        .replaceAll(RegExp(r'[^A-Za-z0-9 ]'), '')
        .trim()
        .replaceAll(RegExp(r'\s+'), '-')
        .toLowerCase();

    return '${stem.isEmpty ? 'lineage' : stem}-lineage.$extension';
  }

  static String _ordinalOrDash(int? number) =>
      number == null ? '-' : '${ordinal(number)} generation';

  static String ordinal(int n) => switch (n % 100) {
    11 || 12 || 13 => '${n}th',
    _ => switch (n % 10) {
      1 => '${n}st',
      2 => '${n}nd',
      3 => '${n}rd',
      _ => '${n}th',
    },
  };
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

  return img.encodeJpg(image, quality: 95);
}

/// The picture form: the same rows, drawn rather than typeset.
class _PictureView extends StatelessWidget {
  const _PictureView({required this.export});

  final LineageExport export;

  static const double _rowHeight = 46;
  static const double _padding = 48;
  static const double _headHeight = 108;

  Size get size =>
      Size(900, _padding * 2 + _headHeight + export.people.length * _rowHeight);

  @override
  Widget build(BuildContext context) {
    final theme = AppTheme.light();

    return MediaQuery(
      data: const MediaQueryData(textScaler: TextScaler.noScaling),
      child: Theme(
        data: theme,
        child: Material(
          color: theme.colorScheme.surface,
          child: SizedBox.fromSize(
            size: size,
            child: Padding(
              padding: const EdgeInsets.all(_padding),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(export.title, style: theme.textTheme.headlineSmall),
                  const SizedBox(height: 4),
                  Text(
                    export._subtitle,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        flex: 3,
                        child: _head(theme, export._outerHeading),
                      ),
                      Expanded(
                        flex: 3,
                        child: _head(
                          theme,
                          export.origin == null ? '' : 'From ${export.origin}',
                        ),
                      ),
                      Expanded(flex: 4, child: _head(theme, 'Name')),
                    ],
                  ),
                  const Divider(),
                  for (final (index, person) in export.people.indexed)
                    SizedBox(
                      height: _rowHeight,
                      child: Row(
                        children: [
                          Expanded(
                            flex: 3,
                            child: Text(
                              LineageExport._ordinalOrDash(
                                person.generation?.outerNumber,
                              ),
                            ),
                          ),
                          Expanded(
                            flex: 3,
                            child: Text(
                              LineageExport._ordinalOrDash(
                                person.generation?.number,
                              ),
                            ),
                          ),
                          Expanded(
                            flex: 4,
                            child: Text(
                              person.displayName,
                              style: TextStyle(
                                fontWeight: index == export.people.length - 1
                                    ? FontWeight.bold
                                    : FontWeight.normal,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _head(ThemeData theme, String text) => Text(
    text,
    style: theme.textTheme.labelMedium?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
      fontWeight: FontWeight.w700,
    ),
  );
}
