/// Every path the app can be at.
///
/// Its own file so the routing decision can be tested without pulling in
/// every screen behind it.
class Routes {
  const Routes._();

  static const String startup = '/';
  static const String signIn = '/sign-in';
  static const String register = '/register';
  static const String forgotPassword = '/forgot-password';
  static const String joinTribe = '/join';
  static const String joinClan = '/join-clan';
  static const String joinRequests = '/join-requests';
  static const String members = '/members';
  static const String claimProfile = '/claim';

  /// The five sections of the bottom bar, in the order they appear there.
  static const String home = '/home';
  static const String tree = '/tree';
  static const String contributions = '/contributions';
  static const String pendingChanges = '/pending';
  static const String profile = '/profile';

  /// A person is addressable so a link to one survives being shared — the
  /// ulid is the public identifier precisely so it can appear in a URL.
  static const String person = '/person';

  /// A child of the tree branch, so finding somebody keeps the bottom bar
  /// and returns to the tree rather than to wherever you came from.
  static const String personSearch = '/tree/search';

  /// The line from the top of the clan down to the signed-in person.
  ///
  /// Not "my generation": that is the name of the application, and a menu
  /// entry that reads like the app you are already in tells nobody anything.
  static const String myLineage = '/tree/my-lineage';

  /// Running a family: asking to start a clan, and appointing the people who
  /// run one. Children of the profile branch, because they are things this
  /// account does rather than places in the archive.
  static const String clanRegistrations = '/profile/clans';
  static const String startClan = '/profile/clans/new';
  static const String committees = '/profile/committee';

  static String personPath(String ulid) => '$person/$ulid';

  static String committeePath(String scopeType, String scopeUlid) =>
      '$committees/$scopeType/$scopeUlid';
}
