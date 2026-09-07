import 'dart:io';

import 'package:flutter/foundation.dart';

import '../../config/env.dart';

/// API v1 paths, in one place.
///
/// The client never builds a URL by string concatenation at a call site: a
/// typo in a path is a runtime 404 that looks like a server problem.
class ApiPaths {
  const ApiPaths._();

  static const String prefix = '/api/v1';

  // Auth
  static const String register = '$prefix/auth/register';
  static const String login = '$prefix/auth/login';
  static const String firebaseExchange = '$prefix/auth/firebase';
  static const String devices = '$prefix/devices';
  static const String logout = '$prefix/auth/logout';
  static const String me = '$prefix/auth/me';
  static const String profile = '$prefix/auth/profile';
  static const String forgotPassword = '$prefix/auth/forgot-password';
  static const String resetPassword = '$prefix/auth/reset-password';
  static const String resendVerificationEmail = '$prefix/auth/email/resend';
  static const String health = '$prefix/health';

  // People
  static const String people = '$prefix/people';
  static String person(String ulid) => '$prefix/people/$ulid';
  static String personFamily(String ulid) => '$prefix/people/$ulid/family';
  static String personRelatives(String ulid) =>
      '$prefix/people/$ulid/relatives';
  static String personTimeline(String ulid) => '$prefix/people/$ulid/timeline';

  static String personRevisions(String ulid) =>
      '$prefix/people/$ulid/revisions';
  static String personDisputes(String ulid) => '$prefix/people/$ulid/disputes';
  static String personVerify(String ulid) => '$prefix/people/$ulid/verify';

  /// Named family lines, for linking somebody who married in to the family
  /// they came from.
  static const String familyBranches = '$prefix/family-branches';
  static String familyBranch(String ulid) => '$familyBranches/$ulid';

  /// Generation labels, which a clan may name for itself.
  static const String generations = '$prefix/generations';

  /// The order the children of one marriage are listed in.
  static String unionChildOrder(String unionUlid) =>
      '$prefix/unions/$unionUlid/children/order';

  // Sync
  static const String syncBatch = '$prefix/sync/batch';

  // Review
  static const String changeRequests = '$prefix/change-requests';
  static String changeRequest(String ulid) => '$prefix/change-requests/$ulid';
  static String approveChange(String ulid) =>
      '$prefix/change-requests/$ulid/approve';
  static String rejectChange(String ulid) =>
      '$prefix/change-requests/$ulid/reject';
  static String withdrawChange(String ulid) =>
      '$prefix/change-requests/$ulid/withdraw';

  static const String disputes = '$prefix/disputes';
  static String resolveDispute(String ulid) => '$prefix/disputes/$ulid/resolve';

  // Chronicle
  static const String personEvents = '$prefix/person-events';
  static const String eventTypes = '$prefix/event-types';

  // Tree
  static String tree(String ulid) => '$prefix/tree/$ulid';
  static String lineage(String ulid) => '$prefix/tree/$ulid/lineage';
  static String pathTo(String from, String to) =>
      '$prefix/tree/$from/path-to/$to';

  // Media
  static String personMedia(String ulid) => '$prefix/people/$ulid/media';
  static const String media = '$prefix/media';

  // Stories
  static const String stories = '$prefix/stories';
  static String story(String ulid) => '$prefix/stories/$ulid';

  // Organisation
  static const String tribes = '$prefix/tribes';
  static const String memberships = '$prefix/memberships';
  static const String profileClaims = '$prefix/profile-claims';

  // Starting a clan, and running one once it exists.
  static const String clanRegistrations = '$prefix/clan-registrations';
  static String approveClanRegistration(String ulid) =>
      '$clanRegistrations/$ulid/approve';
  static String rejectClanRegistration(String ulid) =>
      '$clanRegistrations/$ulid/reject';
  static String withdrawClanRegistration(String ulid) =>
      '$clanRegistrations/$ulid/withdraw';

  /// One clan, and the ancestor its tree begins with.
  static const String clans = '$prefix/clans';
  static String clan(String ulid) => '$clans/$ulid';

  /// The committee: appointments at one scope, plus the two lists a screen
  /// needs before it can offer to make one.
  static const String scopeRoles = '$prefix/scope-roles';
  static const String administeredScopes = '$scopeRoles/administered';
  static const String committeeCandidates = '$scopeRoles/candidates';
}

/// Resolves the API host for whatever the app is running on.
class ApiConfig {
  const ApiConfig._();

  /// "localhost" is not one address.
  ///
  /// An Android emulator reaches the host machine at 10.0.2.2; an iOS simulator
  /// shares the host's loopback; a physical device on the same network needs
  /// the machine's LAN address, which only a build-time define can supply.
  static String get defaultBaseUrl {
    if (Env.apiBaseUrl.isNotEmpty) return Env.apiBaseUrl;

    if (kIsWeb) return 'http://127.0.0.1:8000';

    if (Platform.isAndroid) return 'http://10.0.2.2:8000';

    return 'http://127.0.0.1:8000';
  }
}
