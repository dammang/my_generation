// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'My Generation';

  @override
  String get signIn => 'Sign in';

  @override
  String get signOut => 'Sign out';

  @override
  String get emailLabel => 'Email address';

  @override
  String get passwordLabel => 'Password';

  @override
  String get connectionTitle => 'Connection';

  @override
  String get connectionChecking => 'Checking the connection…';

  @override
  String get connectionOk => 'Connected';

  @override
  String get connectionFailed => 'Cannot reach My Generation';

  @override
  String get retry => 'Try again';

  @override
  String signedInAs(String name) {
    return 'Signed in as $name';
  }

  @override
  String get noClaimedProfile =>
      'This account is not yet linked to a person in the archive.';

  @override
  String claimedProfile(String name) {
    return 'Linked to $name';
  }

  @override
  String permissionsHeld(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count permissions',
      one: '1 permission',
      zero: 'No permissions',
    );
    return '$_temp0';
  }

  @override
  String get loading => 'Loading…';
}
