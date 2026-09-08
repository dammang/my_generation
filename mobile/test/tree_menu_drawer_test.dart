import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/features/tree/view/tree_menu_drawer.dart';

final _scaffold = GlobalKey<ScaffoldState>();

/// Opened as a real drawer, because each entry closes the menu before it acts
/// and a menu with no route to pop behaves differently from one on screen.
Future<void> _open(WidgetTester tester, TreeMenuDrawer drawer) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(key: _scaffold, drawer: drawer),
    ),
  );

  _scaffold.currentState!.openDrawer();

  // Pumped for a fixed time rather than settled: while an export is running
  // the entry shows a spinner, and a spinner never settles.
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
}

void main() {
  testWidgets('both ways of exporting the chart are offered', (tester) async {
    var pictures = 0;
    var documents = 0;

    await _open(
      tester,
      TreeMenuDrawer(
        onGoToMe: () {},
        onExport: () => pictures++,
        onExportPdf: () => documents++,
      ),
    );

    await tester.tap(find.text('Export as a PDF'));
    await tester.pumpAndSettle();

    _scaffold.currentState!.openDrawer();
    await tester.pumpAndSettle();

    await tester.tap(find.text('Export as a picture'));
    await tester.pumpAndSettle();

    expect(documents, 1);
    expect(pictures, 1);
  });

  testWidgets('neither is offered when there is no chart', (tester) async {
    await _open(
      tester,
      TreeMenuDrawer(onGoToMe: () {}, onExport: null, onExportPdf: null),
    );

    // An entry that does nothing is read as a broken one, so both go grey
    // together rather than the PDF staying live over an empty chart.
    for (final label in ['Export as a PDF', 'Export as a picture']) {
      expect(
        tester.widget<ListTile>(find.widgetWithText(ListTile, label)).enabled,
        isFalse,
        reason: '$label was offered with nothing to export',
      );
    }
  });

  testWidgets('nothing can be started while an export is running', (
    tester,
  ) async {
    var started = 0;

    await _open(
      tester,
      TreeMenuDrawer(
        onGoToMe: () {},
        onExport: () => started++,
        onExportPdf: () => started++,
        exporting: true,
      ),
    );

    await tester.tap(find.text('Export as a PDF'), warnIfMissed: false);
    await tester.tap(find.text('Export as a picture'), warnIfMissed: false);
    await tester.pump();

    expect(started, 0);
  });
}
