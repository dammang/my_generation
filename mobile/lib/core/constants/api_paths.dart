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
  static const String logout = '$prefix/auth/logout';
  static const String me = '$prefix/auth/me';
  static const String profile = '$prefix/auth/profile';
  static const String forgotPassword = '$prefix/auth/forgot-password';
  static const String resetPassword = '$prefix/auth/reset-password';
  static const String health = '$prefix/health';

  // People
  static const String people = '$prefix/people';
  static String person(String ulid) => '$prefix/people/$ulid';
  static String personFamily(String ulid) => '$prefix/people/$ulid/family';
  static String personRelatives(String ulid) => '$prefix/people/$ulid/relatives';

  // Tree
  static String tree(String ulid) => '$prefix/tree/$ulid';
  static String lineage(String ulid) => '$prefix/tree/$ulid/lineage';
  static String pathTo(String from, String to) => '$prefix/tree/$from/path-to/$to';

  // Organisation
  static const String tribes = '$prefix/tribes';
  static const String memberships = '$prefix/memberships';
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
