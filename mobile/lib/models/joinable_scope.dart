/// Something an account can ask to join, as the joining list shows it.
///
/// Tribes and clans are asked for through the same endpoint and reviewed the
/// same way, so they are offered through one screen. Written as one type
/// rather than two lists that happen to look alike: the two screens drifted
/// apart in every other part of this app that tried it.
class JoinableScope {
  const JoinableScope({
    required this.type,
    required this.ulid,
    required this.name,
    required this.subtitle,
    this.nativeName,
    this.description,
  });

  /// What the server calls the scope: 'tribe' or 'clan'.
  final String type;

  final String ulid;
  final String name;

  /// Enough to tell two similarly named entries apart without opening either.
  final String subtitle;

  final String? nativeName;
  final String? description;
}
