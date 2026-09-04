import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../providers/sync_provider.dart';

/// The frame the five main sections live inside.
///
/// Every section used to be a spoke pushed on top of home, reachable only from
/// home and leavable only with the back button — so nothing but home advertised
/// that the tree or the review queue existed at all.
///
/// The branches are an IndexedStack, which is the point: each tab keeps its own
/// navigation stack and its own scroll position. Panning the tree, tapping
/// across to contributions and coming back returns to the same view of the same
/// family, rather than a chart reset to the top.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Read from the local queue rather than the server, so the count is
    // correct on a phone with no signal — which is exactly when it matters.
    final sync = ref.watch(syncControllerProvider);

    return Scaffold(
      body: shell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: shell.currentIndex,
        onDestinationSelected: (index) => shell.goBranch(
          index,
          // Tapping the tab you are already on returns to the root of that
          // section, which is the behaviour people expect from every other app
          // and the only way back out of a deep stack without the back button.
          initialLocation: index == shell.currentIndex,
        ),
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Home',
          ),
          const NavigationDestination(
            icon: Icon(Icons.account_tree_outlined),
            selectedIcon: Icon(Icons.account_tree),
            label: 'Tree',
          ),
          // "Edits" rather than "Contributions": five labels have to fit across
          // a narrow phone, and a truncated word reads as a bug. It covers both
          // sides of that screen — edits somebody proposed, and edits waiting
          // on them — which "Review" would not for a plain contributor.
          const NavigationDestination(
            icon: Icon(Icons.rate_review_outlined),
            selectedIcon: Icon(Icons.rate_review),
            label: 'Edits',
          ),
          NavigationDestination(
            icon: _OutboxIcon(sync: sync, selected: false),
            selectedIcon: _OutboxIcon(sync: sync, selected: true),
            label: 'Outbox',
          ),
          const NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}

/// The unsent-work count, coloured by whether anything needs a person.
///
/// Something merely queued will send itself; something refused will not, and
/// will sit there indefinitely until somebody looks. Those two deserve
/// different colours, because only one of them is asking for attention.
class _OutboxIcon extends StatelessWidget {
  const _OutboxIcon({required this.sync, required this.selected});

  final SyncState sync;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final count = sync.pending.length;

    final icon = Icon(selected ? Icons.outbox : Icons.outbox_outlined);

    if (count == 0) return icon;

    return Badge.count(
      count: count,
      backgroundColor: sync.rejectedCount > 0 ? theme.colorScheme.error : null,
      child: icon,
    );
  }
}
