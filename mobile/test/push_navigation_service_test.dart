import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:my_generation/services/push_navigation_service.dart';

/// A minimal two-route app, standing in for the real one: what is under test
/// is whether PushNavigationService drives a GoRouter correctly, not the
/// app's actual route table.
GoRouter _testRouter() => GoRouter(
  initialLocation: '/start',
  routes: [
    GoRoute(path: '/start', builder: (_, _) => const Text('start screen')),
    GoRoute(path: '/home', builder: (_, _) => const Text('home screen')),
  ],
);

Future<void> _pump(WidgetTester tester, GoRouter router) =>
    tester.pumpWidget(MaterialApp.router(routerConfig: router));

void main() {
  group('PushNavigationService', () {
    testWidgets('a tap while the app is running navigates to the route it names', (tester) async {
      final router = _testRouter();
      await _pump(tester, router);

      final taps = StreamController<RemoteMessage>();
      // getInitialMessage() has no platform binding in a widget test and
      // fails silently by design — start() treats that as "nothing was
      // waiting", which is what actually happens on a phone that was not
      // cold-started by a notification either.
      await PushNavigationService(router, openedAppTaps: taps.stream).start();

      taps.add(RemoteMessage(data: {'route': '/home'}));
      await tester.pumpAndSettle();

      expect(find.text('home screen'), findsOneWidget);
      await taps.close();
    });

    testWidgets('a tap with no route data does nothing, not crash', (tester) async {
      final router = _testRouter();
      await _pump(tester, router);

      final taps = StreamController<RemoteMessage>();
      await PushNavigationService(router, openedAppTaps: taps.stream).start();

      // No `route` key — the shape of a push that carries only a title and
      // body, or a malformed one.
      taps.add(RemoteMessage(data: const {}));
      await tester.pumpAndSettle();

      expect(find.text('start screen'), findsOneWidget);
      await taps.close();
    });

    testWidgets('an empty route string does nothing, not crash', (tester) async {
      final router = _testRouter();
      await _pump(tester, router);

      final taps = StreamController<RemoteMessage>();
      await PushNavigationService(router, openedAppTaps: taps.stream).start();

      taps.add(RemoteMessage(data: {'route': ''}));
      await tester.pumpAndSettle();

      expect(find.text('start screen'), findsOneWidget);
      await taps.close();
    });
  });
}
