import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../routing/app_router.dart';

/// Everything the chart can do that is not finding somebody.
///
/// Four icons across the top of a chart is four things competing with the
/// thing they sit on top of, and the chart is the product. Only search stays
/// out here, because it is the one thing somebody reaches for mid-gesture.
class TreeMenuDrawer extends StatelessWidget {
  const TreeMenuDrawer({
    super.key,
    required this.onGoToMe,
    required this.onExport,
    required this.onExportPdf,
    this.exporting = false,
  });

  final VoidCallback onGoToMe;

  /// Null where there is no chart to export. An entry that does nothing is
  /// read as a broken one.
  final VoidCallback? onExport;

  /// The same chart as a document rather than a photograph.
  final VoidCallback? onExportPdf;

  final bool exporting;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Drawer(
      child: SafeArea(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 24, 20, 12),
              child: Text('Family tree', style: theme.textTheme.headlineSmall),
            ),
            ListTile(
              leading: const Icon(Icons.format_list_numbered),
              title: const Text('My lineage'),
              subtitle: const Text('The line from the top of the clan to you'),
              onTap: () {
                Navigator.of(context).pop();
                context.push(Routes.myLineage);
              },
            ),
            ListTile(
              leading: const Icon(Icons.my_location),
              title: const Text('Go to me'),
              subtitle: const Text('Centre the chart on your own record'),
              onTap: () {
                Navigator.of(context).pop();
                onGoToMe();
              },
            ),
            const Divider(),
            // Two formats because they answer different questions: a document
            // to read and print, a picture to send to somebody who will only
            // ever look at it on a phone.
            ListTile(
              leading: exporting
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.picture_as_pdf_outlined),
              title: const Text('Export as a PDF'),
              subtitle: const Text('Sharp at any size, and printable'),
              enabled: onExportPdf != null && !exporting,
              onTap: () {
                Navigator.of(context).pop();
                onExportPdf?.call();
              },
            ),
            ListTile(
              leading: exporting
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.ios_share),
              title: const Text('Export as a picture'),
              subtitle: const Text('The whole chart, up to 6K'),
              enabled: onExport != null && !exporting,
              onTap: () {
                Navigator.of(context).pop();
                onExport?.call();
              },
            ),
          ],
        ),
      ),
    );
  }
}
