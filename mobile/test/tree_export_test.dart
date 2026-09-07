import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:image/image.dart' as img;
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/export/tree_export_image.dart';
import 'package:my_generation/features/tree/export/tree_export_view.dart';
import 'package:my_generation/features/tree/layout/tree_layout_engine.dart';
import 'package:my_generation/models/person_summary.dart';
import 'package:my_generation/models/tree_graph.dart';
import 'package:my_generation/models/tree_summary.dart';

PersonSummary _person(String ulid, int depth) => PersonSummary(
  ulid: ulid,
  displayName: ulid,
  gender: 'unknown',
  isLiving: false,
  redacted: false,
  depth: depth,
);

TreeGraph _graph(int children) => TreeGraph(
  focusUlid: 'father',
  people: {
    'father': _person('father', 0),
    for (var i = 0; i < children; i++) 'child$i': _person('child$i', 1),
  },
  unions: [
    TreeUnion(
      ulid: 'u1',
      partnerUlids: const ['father'],
      childUlids: [for (var i = 0; i < children; i++) 'child$i'],
    ),
  ],
  edges: [
    for (var i = 0; i < children; i++)
      TreeEdge(parentUlid: 'father', childUlid: 'child$i'),
  ],
  expandable: const {},
);

void main() {
  group('how large to render', () {
    test('a small chart is magnified rather than left tiny', () {
      final scale = ExportScale.forCanvas(const Size(400, 300));

      // A tree of six people should not be exported at postage-stamp size
      // just because it happens to fit.
      expect(scale.pixelRatio, ExportScale.maxRatio);
      expect(scale.pixels, const Size(1600, 1200));
    });

    test('a wide chart stops at the long edge', () {
      final scale = ExportScale.forCanvas(const Size(9000, 1000));

      expect(scale.pixels.width, ExportScale.maxEdge);
      expect(scale.pixelRatio, lessThan(1));
    });

    test('a big chart stops on total pixels, not just the edge', () {
      // 6000 x 5000 would be inside the 6K edge and still 150 megapixels once
      // magnified — six hundred megabytes of raw pixels, which is a phone
      // running out of memory partway through the encode.
      final scale = ExportScale.forCanvas(const Size(6000, 5000));

      expect(
        scale.pixels.width * scale.pixels.height,
        lessThanOrEqualTo(ExportScale.maxPixels),
      );
      expect(scale.pixels.width, lessThanOrEqualTo(ExportScale.maxEdge));
    });

    test('the longest edge of a 6K export is 6K', () {
      final scale = ExportScale.forCanvas(const Size(3072, 500));

      expect(scale.pixels.width, ExportScale.maxEdge);
    });
  });

  group('the caption', () {
    TreeSummary summary(List<Map<String, dynamic>> generations) =>
        TreeSummary.fromJson({
          'person': {
            'ulid': 'father',
            'display_name': 'Thawng Dam',
            'gender': 'male',
            'is_living': false,
            'redacted': false,
            'generation': {
              'number': 1,
              'origin': 'Jasuan',
              'outer_number': 11,
              'outer_origin': 'Pu Zo',
            },
          },
          'generations': generations,
          'total': generations.fold<int>(
            0,
            (sum, g) => sum + (g['total'] as int),
          ),
          'hidden': 0,
        });

    Future<void> pumpCaption(WidgetTester tester, TreeSummary? given) async {
      final graph = _graph(3);
      final view = TreeExportView(
        graph: graph,
        layout: const TreeLayoutEngine().layout(graph),
        title: 'Thawng Dam',
        summary: given,
      );

      await tester.binding.setSurfaceSize(view.size);
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(MaterialApp(home: view));
      await tester.pumpAndSettle();
    }

    testWidgets('says where they stand on both of the clan\'s scales', (
      tester,
    ) async {
      await pumpCaption(tester, summary([]));

      expect(find.text('11th generation from Pu Zo'), findsOneWidget);
      expect(find.text('1st generation of Jasuan'), findsOneWidget);
    });

    testWidgets('counts each remove the way a family says it', (tester) async {
      await pumpCaption(
        tester,
        summary([
          {'depth': 1, 'male': 7, 'female': 2, 'unknown': 0, 'total': 9},
          {'depth': 2, 'male': 18, 'female': 13, 'unknown': 0, 'total': 31},
          {'depth': 3, 'male': 20, 'female': 22, 'unknown': 1, 'total': 43},
        ]),
      );

      expect(find.text('Had 7 sons, 2 daughters'), findsOneWidget);
      expect(find.text('Had 18 grandsons, 13 granddaughters'), findsOneWidget);
      expect(
        find.text('Had 20 great-grandsons, 22 great-granddaughters, 1 more'),
        findsOneWidget,
      );
      expect(find.text('83 descendants in all'), findsOneWidget);
    });

    testWidgets('falls back to a plain count where sex was never recorded', (
      tester,
    ) async {
      await pumpCaption(
        tester,
        summary([
          {'depth': 1, 'male': 0, 'female': 0, 'unknown': 5, 'total': 5},
        ]),
      );

      // Most of an oral archive records a name and a relationship and nothing
      // else. "5 sons" would be an invention.
      expect(find.text('Had 5 people'), findsOneWidget);
    });

    testWidgets('a chart still exports when the count cannot be had', (
      tester,
    ) async {
      await pumpCaption(tester, null);

      // The count is one request that can fail; the picture is the thing
      // somebody asked for.
      expect(find.text('Thawng Dam'), findsOneWidget);
      expect(find.textContaining('descendants in all'), findsNothing);
    });
  });

  group('the short form the card uses', () {
    TreeSummary of(List<Map<String, dynamic>> generations) =>
        TreeSummary.fromJson({
          'person': {
            'ulid': 'p',
            'display_name': 'p',
            'gender': 'male',
            'is_living': false,
            'redacted': false,
          },
          'generations': generations,
          'total': generations.fold<int>(
            0,
            (sum, g) => sum + (g['total'] as int),
          ),
          'hidden': 0,
        });

    test('names the children and counts the rest', () {
      final summary = of([
        {'depth': 1, 'male': 7, 'female': 2, 'unknown': 0, 'total': 9},
        {'depth': 2, 'male': 18, 'female': 13, 'unknown': 0, 'total': 31},
        {'depth': 3, 'male': 20, 'female': 23, 'unknown': 0, 'total': 43},
      ]);

      // Three removes and the total: great-grandchildren are as far as a
      // living person usually counts, and past that the card would grow
      // without limit while describing a chart it was pushing off the screen.
      expect(
        summary.shortly,
        '7 sons, 2 daughters · 31 grandchildren · '
        '43 great-grandchildren · 83 descendants',
      );
    });

    test('stops after the great-grandchildren', () {
      final summary = of([
        {'depth': 1, 'male': 2, 'female': 0, 'unknown': 0, 'total': 2},
        {'depth': 2, 'male': 4, 'female': 0, 'unknown': 0, 'total': 4},
        {'depth': 3, 'male': 8, 'female': 0, 'unknown': 0, 'total': 8},
        {'depth': 4, 'male': 16, 'female': 0, 'unknown': 0, 'total': 16},
      ]);

      expect(summary.shortly, isNot(contains('great-great')));
      expect(summary.shortly, endsWith('30 descendants'));
    });

    test('says nothing where nobody descends from them', () {
      expect(of([]).shortly, isNull);
    });
  });

  testWidgets('the export draws everybody, not what fits a screen', (
    tester,
  ) async {
    // More people than a phone can show at once. The screen culls to its
    // viewport; a picture of the family must not.
    final graph = _graph(40);
    final layout = const TreeLayoutEngine().layout(graph);

    final view = TreeExportView(
      graph: graph,
      layout: layout,
      title: 'Whitfield',
    );

    await tester.binding.setSurfaceSize(view.size);
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(MaterialApp(home: view));
    await tester.pumpAndSettle();

    for (var i = 0; i < 40; i++) {
      expect(
        find.text('child$i'),
        findsOneWidget,
        reason: 'child$i is missing',
      );
    }

    // And it says whose tree it is, because a chart with no caption is one
    // nobody can file.
    expect(find.text('Whitfield'), findsOneWidget);
  });

  testWidgets('the chart renders and encodes to a real jpeg', (tester) async {
    final graph = _graph(6);
    final layout = const TreeLayoutEngine().layout(graph);

    final view = TreeExportView(
      graph: graph,
      layout: layout,
      title: 'Whitfield',
    );

    // The whole pipeline: its own view, its own layout and paint, at a size
    // no screen in the test has. This is the part that either works or
    // produces a blank rectangle, and a blank rectangle looks like a success.
    //
    // Inside runAsync because rasterising is real asynchronous work, and the
    // fake clock a widget test runs on never lets it finish — the call simply
    // never returns.
    late final ui.Image image;
    late final Uint8List raw;

    await tester.runAsync(() async {
      image = await renderOffscreen(
        widget: view,
        size: view.size,
        pixelRatio: 1,
        view: tester.view,
      );

      final data = await image.toByteData(format: ui.ImageByteFormat.rawRgba);
      raw = data!.buffer.asUint8List();
    });

    expect(image.width, view.size.width.round());
    expect(image.height, view.size.height.round());

    final decoded = img.Image.fromBytes(
      width: image.width,
      height: image.height,
      bytes: raw.buffer,
      numChannels: 4,
      order: img.ChannelOrder.rgba,
    );

    // Something was actually drawn: a chart on a light ground is not one
    // colour, and an empty pipeline paints exactly one.
    final colours = <int>{};

    for (var y = 0; y < decoded.height; y += 17) {
      for (var x = 0; x < decoded.width; x += 17) {
        colours.add(decoded.getPixel(x, y).hashCode);
      }
    }

    expect(colours.length, greaterThan(1), reason: 'the picture is blank');

    final jpeg = img.encodeJpg(decoded, quality: 95);

    // SOI marker. A file the phone will not open is not an export.
    expect(jpeg.sublist(0, 2), [0xFF, 0xD8]);

    image.dispose();
  });
}
