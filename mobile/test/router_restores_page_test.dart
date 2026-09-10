import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/routing/route_decision.dart';
import 'package:my_generation/routing/routes.dart';

String? _decide(
  String path, {
  bool tokenChecked = true,
  bool signedIn = true,
  bool mustJoin = false,
}) {
  final uri = Uri.parse(path);

  return RouteDecision.forRequest(
    location: uri.path,
    uri: uri,
    tokenChecked: tokenChecked,
    signedIn: signedIn,
    mustJoin: mustJoin,
  );
}

void main() {
  test('a page loaded before the token is checked carries where it went', () {
    // Every page load begins here. It used to wait at startup having forgotten
    // the page — and startup is an entry route, so once the token checked out
    // everybody arrived at home instead of what they had refreshed.
    final waiting = _decide('/profile', tokenChecked: false);

    expect(waiting, isNotNull);
    expect(Uri.parse(waiting!).path, Routes.startup);
    expect(Uri.parse(waiting).queryParameters['from'], '/profile');
  });

  test('and goes back to it once the token checks out', () {
    expect(_decide('/?from=%2Fprofile'), '/profile');
  });

  test('a page deeper than one segment comes back whole', () {
    // The query is carried encoded, so a path with its own parameters has to
    // survive the round trip rather than arriving truncated.
    final waiting = _decide('/person/01ABC?tab=family', tokenChecked: false);
    final from = Uri.parse(waiting!).queryParameters['from'];

    expect(from, '/person/01ABC?tab=family');
    expect(_decide('/?from=${Uri.encodeComponent(from!)}'), from);
  });

  test('with nowhere to go back to, home is right', () {
    expect(_decide('/'), Routes.home);
  });

  test('it will not send itself back to the waiting room', () {
    // startup is "/", and every path begins with that: a prefix check here
    // rejected every destination and sent everybody to home regardless.
    expect(_decide('/?from=%2F'), Routes.home);
  });

  test('the waiting room does not redirect to itself', () {
    expect(_decide('/', tokenChecked: false), isNull);
  });

  test('somebody signed out is sent to sign in, wherever they asked for', () {
    expect(_decide('/profile', signedIn: false), Routes.signIn);
    expect(_decide('/sign-in', signedIn: false), isNull);
    expect(_decide('/register', signedIn: false), isNull);
  });

  test('joining comes before the page they asked for', () {
    // Somebody with no membership can see almost nothing, so the question is
    // worth interrupting for — including interrupting a restored page.
    expect(_decide('/?from=%2Fprofile', mustJoin: true), Routes.joinTribe);
    expect(_decide('/join', mustJoin: true), isNull);
  });

  test('a signed-in person already on a page is left alone', () {
    expect(_decide('/tree'), isNull);
    expect(_decide('/members'), isNull);
  });
}
