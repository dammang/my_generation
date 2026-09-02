/// The signed-in account.
///
/// A user is not a person: `person` is populated only when this account has
/// been verified as a genealogy record through an approved profile claim, which
/// most accounts never do.
class ApiUser {
  const ApiUser({
    required this.ulid,
    required this.name,
    required this.email,
    required this.locale,
    required this.status,
    required this.emailVerified,
    required this.isSuperAdmin,
    required this.permissions,
    required this.tribeIds,
    required this.clanIds,
    required this.branchIds,
    this.personUlid,
    this.personName,
  });

  final String ulid;
  final String name;
  final String email;
  final String locale;
  final String status;
  final bool emailVerified;
  final bool isSuperAdmin;
  final List<String> permissions;
  final List<int> tribeIds;
  final List<int> clanIds;
  final List<int> branchIds;

  /// The genealogy record this account has been verified as, if any.
  final String? personUlid;
  final String? personName;

  bool get hasClaimedPerson => personUlid != null;

  bool can(String permission) => isSuperAdmin || permissions.contains(permission);

  factory ApiUser.fromJson(Map<String, dynamic> json) {
    final scopes = (json['scopes'] as Map?)?.cast<String, dynamic>() ?? const {};
    final person = (json['person'] as Map?)?.cast<String, dynamic>();

    return ApiUser(
      ulid: json['ulid'] as String,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      locale: json['locale'] as String? ?? 'en',
      status: json['status'] as String? ?? 'active',
      emailVerified: json['email_verified'] as bool? ?? false,
      isSuperAdmin: json['is_super_admin'] as bool? ?? false,
      permissions: _strings(json['permissions']),
      tribeIds: _ints(scopes['tribe_ids']),
      clanIds: _ints(scopes['clan_ids']),
      branchIds: _ints(scopes['branch_ids']),
      personUlid: person?['ulid'] as String?,
      personName: person?['display_name'] as String?,
    );
  }

  static List<String> _strings(dynamic raw) =>
      (raw as List?)?.map((e) => e.toString()).toList(growable: false) ?? const [];

  static List<int> _ints(dynamic raw) =>
      (raw as List?)?.map((e) => int.tryParse(e.toString()) ?? 0).toList(growable: false) ?? const [];
}
