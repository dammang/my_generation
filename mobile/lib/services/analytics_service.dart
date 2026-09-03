import 'package:firebase_analytics/firebase_analytics.dart';
import 'package:flutter/foundation.dart';

/// Usage measurement, kept deliberately blunt.
///
/// This application is a record of real families, including living people. What
/// is sent is which screens are opened and whether a flow completed — never a
/// name, a date, an identifier, or anything that could be joined back to a
/// person. A genealogy archive is exactly the kind of data that should not be
/// accumulating in somebody else's analytics warehouse, and the way to
/// guarantee that is to never put it in an event.
class AnalyticsService {
  AnalyticsService({FirebaseAnalytics? analytics})
      : _analytics = analytics ?? FirebaseAnalytics.instance;

  final FirebaseAnalytics _analytics;

  /// Off in debug: a developer's own navigation is not usage.
  Future<void> start() => _analytics.setAnalyticsCollectionEnabled(!kDebugMode);

  Future<void> screen(String name) => _analytics.logScreenView(screenName: name);

  /// A named milestone with no payload — "somebody added a relative", never
  /// which relative, for whom, or when they were born.
  Future<void> milestone(String name) => _analytics.logEvent(name: name);

  /// Deliberately not `setUserId`. Tying a Firebase analytics profile to an
  /// account identifier is what turns aggregate counts into a record of what a
  /// named person looked at.
  Future<void> clearIdentity() => _analytics.setUserId(id: null);
}
