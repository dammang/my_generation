/// A tribe or clan this account may appoint people to.
class AdministeredScope {
  const AdministeredScope({
    required this.scopeType,
    required this.scopeUlid,
    required this.name,
    required this.assignableRoles,
  });

  final String scopeType;
  final String scopeUlid;
  final String name;

  /// What this account may hand out here, which is not the same list for
  /// everybody: nobody may grant a role carrying authority they lack.
  final List<String> assignableRoles;

  factory AdministeredScope.fromJson(Map<String, dynamic> json) =>
      AdministeredScope(
        scopeType: json['scope_type'] as String? ?? 'clan',
        scopeUlid: json['scope_ulid'] as String,
        name: json['name'] as String? ?? 'Unnamed',
        assignableRoles: _strings(json['assignable_roles']),
      );
}

/// One person holding one role at one scope.
///
/// Flat rather than grouped by person, because a role is what gets granted and
/// what gets taken back.
class Appointment {
  const Appointment({
    required this.userUlid,
    required this.userName,
    required this.role,
    this.grantedBy,
  });

  final String userUlid;
  final String userName;
  final String role;
  final String? grantedBy;

  factory Appointment.fromJson(Map<String, dynamic> json) {
    final user = (json['user'] as Map?)?.cast<String, dynamic>() ?? const {};

    return Appointment(
      userUlid: user['ulid'] as String,
      userName: user['name'] as String? ?? 'Unnamed',
      role: json['role'] as String? ?? 'member',
      grantedBy: json['granted_by'] as String?,
    );
  }
}

/// Somebody who could be appointed, and what they already hold here.
class CommitteeCandidate {
  const CommitteeCandidate({
    required this.userUlid,
    required this.userName,
    required this.roles,
  });

  final String userUlid;
  final String userName;
  final List<String> roles;

  factory CommitteeCandidate.fromJson(Map<String, dynamic> json) {
    final user = (json['user'] as Map?)?.cast<String, dynamic>() ?? const {};

    return CommitteeCandidate(
      userUlid: user['ulid'] as String,
      userName: user['name'] as String? ?? 'Unnamed',
      roles: _strings(json['roles']),
    );
  }
}

/// Role names as the server knows them, said the way a family would.
String roleLabel(String role) => switch (role) {
  'tribe-admin' => 'Tribe administrator',
  'clan-admin' => 'Clan administrator',
  'family-admin' => 'Family administrator',
  'historian' => 'Historian',
  'contributor' => 'Contributor',
  'member' => 'Member',
  'viewer' => 'Viewer',
  _ => role,
};

/// What the role actually lets somebody do, in the terms they will ask about.
String roleDescription(String role) => switch (role) {
  'tribe-admin' => 'Runs the whole tribe, including every clan under it.',
  'clan-admin' =>
    'Runs this clan: approves members, edits records, appoints others.',
  'family-admin' => 'Looks after one family line and approves changes to it.',
  'historian' => 'Verifies facts and settles disputes, but manages no members.',
  'contributor' => 'Adds and edits records. Changes still go to review.',
  'member' => 'Can see the family, and nothing more.',
  'viewer' => 'Public records only.',
  _ => '',
};

List<String> _strings(dynamic raw) =>
    (raw as List?)?.map((e) => e.toString()).toList(growable: false) ??
    const [];
