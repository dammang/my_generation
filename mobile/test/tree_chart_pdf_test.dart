import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'dart:ui';

import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/export/tree_chart_pdf.dart';
import 'package:my_generation/features/tree/layout/tree_layout.dart';
import 'package:my_generation/features/tree/layout/tree_layout_engine.dart';
import 'package:my_generation/models/tree_graph.dart';

TreeGraph _graph(String fixture) => TreeGraph.fromResponse(
  jsonDecode(File('test/fixtures/$fixture.json').readAsStringSync())
      as Map<String, dynamic>,
  const {},
);

TreeChartPdf _chart(String fixture) {
  final graph = _graph(fixture);

  return TreeChartPdf(
    graph: graph,
    layout: const TreeLayoutEngine().layout(graph),
    title: 'Thawng Dam',
  );
}

/// Everything a PDF holds is compressed, so the text is not readable from the
/// bytes as they stand. Inflating the streams is how a reader gets at it, and
/// it is the only way a test can tell drawn text from a picture of text.
String _inflated(Uint8List bytes) {
  final raw = latin1.decode(bytes, allowInvalid: true);
  final body = StringBuffer(raw);
  final pattern = RegExp(r'stream\r?\n');

  for (final match in pattern.allMatches(raw)) {
    final end = raw.indexOf('endstream', match.end);
    if (end < 0) continue;

    try {
      body.write(
        latin1.decode(
          ZLibCodec().decode(bytes.sublist(match.end, end)),
          allowInvalid: true,
        ),
      );
    } catch (_) {
      // Not a deflated stream; nothing to read from it.
    }
  }

  return body.toString();
}

void main() {
  test('the chart is a real pdf', () async {
    final bytes = await _chart('tree_jasuan').bytes();

    expect(String.fromCharCodes(bytes.take(4)), '%PDF');
  });

  test('it is a drawing, not a photograph of one', () async {
    final bytes = await _chart('deep_thawng_dam').bytes();
    final body = _inflated(bytes);

    // The whole point of the document over the picture. An image would show up
    // as an encoded XObject and would run to megabytes.
    expect(body, isNot(contains('DCTDecode')));
    expect(body, isNot(contains('/Subtype /Image')));
    expect(bytes.length, lessThan(2000000));

    // Real strokes and real text, not an empty page that merely fails to be a
    // photograph: `l` and `c` are path operators, `TJ` shows a string.
    expect(
      'TJ'.allMatches(body).length,
      greaterThan(20),
      reason: 'the page has no text on it',
    );
    expect(
      RegExp(r'\bl\b').allMatches(body).length,
      greaterThan(20),
      reason: 'the page has no connectors on it',
    );
  });

  test('every person on the chart is written into the document', () async {
    final graph = _graph('tree_jasuan');
    final chart = TreeChartPdf(
      graph: graph,
      layout: const TreeLayoutEngine().layout(graph),
      title: 'Jasuan',
    );

    final body = _inflated(await chart.bytes());

    // The fixtures are privacy-masked, so every name is the same word — what
    // is checked is that there is one drawn per card, which is what makes the
    // document searchable rather than a diagram of empty boxes.
    expect(
      RegExp(r'\(Private\)|\(P\)').allMatches(body).length,
      greaterThanOrEqualTo(graph.people.length),
      reason: 'somebody on the chart was drawn without their name',
    );
  });

  test('a chart too wide for a page is scaled, not truncated', () async {
    final graph = _graph('tree_jasuan');
    final drawn = const TreeLayoutEngine().layout(graph);

    // No real family in the archive is wide enough to reach the limit yet, and
    // a test that never reaches it proves only that small charts are small.
    // This is the same chart spread until it cannot fit on a page.
    const stretch = 12.0;
    final huge = TreeLayout(
      nodes: {
        for (final entry in drawn.nodes.entries)
          entry.key: NodeBox(
            ulid: entry.key,
            rect: Rect.fromLTWH(
              entry.value.rect.left * stretch,
              entry.value.rect.top * stretch,
              entry.value.rect.width * stretch,
              entry.value.rect.height * stretch,
            ),
            depth: entry.value.depth,
          ),
      },
      unionShapes: const [],
      looseEdges: const [],
      canvasSize: drawn.canvasSize * stretch,
      focusRect: drawn.focusRect,
    );

    expect(
      huge.canvasSize.width,
      greaterThan(TreeChartPdf.maxSide),
      reason:
          'the chart under test is not too big to fit, so nothing is being '
          'tested',
    );

    final body = latin1.decode(
      await TreeChartPdf(graph: graph, layout: huge, title: 'Jasuan').bytes(),
      allowInvalid: true,
    );

    final box = RegExp(
      r'/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)',
    ).firstMatch(body);

    expect(box, isNotNull, reason: 'the page declares no size');

    final width = double.parse(box!.group(3)!);
    final height = double.parse(box.group(4)!);

    // A PDF page may not exceed 14400 units on a side; a reader rejects one
    // that does, and the chart would have been for nothing.
    expect(width, lessThanOrEqualTo(TreeChartPdf.maxSide));
    expect(height, lessThanOrEqualTo(TreeChartPdf.maxSide));

    // Scaled to fit rather than cropped to fit: the whole chart is still there,
    // in the proportions it was laid out in.
    expect(
      width / height,
      closeTo(huge.canvasSize.width / huge.canvasSize.height, 0.2),
    );
  });
}
