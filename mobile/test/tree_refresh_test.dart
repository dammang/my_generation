import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/providers/app_providers.dart';
import 'package:my_generation/providers/person_provider.dart';
import 'package:my_generation/providers/tree_provider.dart';

import 'support/fake_api.dart';

const _ulid = '01THAWNGTHAWNGTHAWNGTHAWNG';

/// A window onto a real WidgetRef, since both functions under test take one.
class _Probe extends ConsumerWidget {
  const _Probe({required this.onRef});

  final void Function(WidgetRef) onRef;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    onRef(ref);

    // Watched, as the chart itself watches it. A provider nobody is listening
    // to never runs, so without this the whole test measured zero fetches
    // against zero fetches and would have passed however eager the code was.
    ref.watch(treeProvider);

    return const SizedBox.shrink();
  }
}

Future<({FakeAdapter adapter, WidgetRef ref})> _pump(
  WidgetTester tester,
) async {
  final adapter = FakeAdapter({});
  late WidgetRef captured;

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(fakeApiClient(adapter)),
        secureStorageProvider.overrideWithValue(FakeSecureStorage()),
      ],
      child: MaterialApp(home: _Probe(onRef: (ref) => captured = ref)),
    ),
  );

  await tester.pumpAndSettle();

  return (adapter: adapter, ref: captured);
}

int _treeFetches(FakeAdapter adapter) =>
    adapter.received.where((r) => r.path.contains('/tree')).length;

void main() {
  testWidgets('a change marks the chart stale instead of fetching it', (
    tester,
  ) async {
    final (:adapter, :ref) = await _pump(tester);

    // The chart has been looked at once, so it is worth keeping up to date.
    ref.read(treeQueryProvider.notifier).focusOn(_ulid);
    await tester.pumpAndSettle();

    final drawn = _treeFetches(adapter);

    expect(drawn, greaterThan(0), reason: 'the chart was never fetched at all');

    // Somebody adds a relative, corrects a name, approves a change.
    invalidatePerson(ref, _ulid);
    await tester.pumpAndSettle();

    expect(ref.read(treeStaleProvider), isTrue);
    expect(
      _treeFetches(adapter),
      drawn,
      reason:
          'the whole chart was fetched again for a change nobody was '
          'looking at the chart to see',
    );
  });

  testWidgets('and fetches it when somebody comes back to it', (tester) async {
    final (:adapter, :ref) = await _pump(tester);

    ref.read(treeQueryProvider.notifier).focusOn(_ulid);
    await tester.pumpAndSettle();

    invalidatePerson(ref, _ulid);
    await tester.pumpAndSettle();

    final before = _treeFetches(adapter);

    expect(
      before,
      greaterThan(0),
      reason: 'the chart was never fetched at all',
    );

    refreshTreeIfStale(ref);
    await tester.pumpAndSettle();

    expect(ref.read(treeStaleProvider), isFalse);
    expect(_treeFetches(adapter), before + 1);
  });

  testWidgets('several changes collapse into one fetch', (tester) async {
    // The case this is really for: sitting with a relative and typing in their
    // children, one after another, without the chart being pulled down between
    // each one.
    final (:adapter, :ref) = await _pump(tester);

    ref.read(treeQueryProvider.notifier).focusOn(_ulid);
    await tester.pumpAndSettle();

    final drawn = _treeFetches(adapter);

    expect(drawn, greaterThan(0), reason: 'the chart was never fetched at all');

    for (var i = 0; i < 5; i++) {
      invalidatePerson(ref, _ulid);
      await tester.pumpAndSettle();
    }

    expect(_treeFetches(adapter), drawn);

    refreshTreeIfStale(ref);
    await tester.pumpAndSettle();

    expect(_treeFetches(adapter), drawn + 1);
  });

  testWidgets('coming back with nothing changed fetches nothing', (
    tester,
  ) async {
    final (:adapter, :ref) = await _pump(tester);

    ref.read(treeQueryProvider.notifier).focusOn(_ulid);
    await tester.pumpAndSettle();

    final drawn = _treeFetches(adapter);

    expect(drawn, greaterThan(0), reason: 'the chart was never fetched at all');

    refreshTreeIfStale(ref);
    await tester.pumpAndSettle();

    expect(_treeFetches(adapter), drawn);
  });
}
