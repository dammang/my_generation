import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/models/membership.dart';
import 'package:my_generation/models/profile_claim.dart';
import 'package:my_generation/models/tribe_summary.dart';

void main() {
  group('TribeSummary', () {
    test('builds a subtitle that distinguishes similarly named tribes', () {
      final tribe = TribeSummary.fromJson({
        'ulid': '01H',
        'name': 'Zomi',
        'region': 'Chin State',
        'country_code': 'MM',
        'counts': {'people': 217, 'clans': 4},
      });

      expect(tribe.subtitle, 'Chin State, MM · 217 people');
    });

    test('falls back to the size when no place is recorded', () {
      final tribe = TribeSummary.fromJson({
        'ulid': '01H',
        'name': 'Zomi',
        'counts': {'people': 1},
      });

      expect(tribe.subtitle, '1 person');
    });

    test('survives a tribe with no counts at all', () {
      final tribe = TribeSummary.fromJson({'ulid': '01H', 'name': 'New'});

      expect(tribe.peopleCount, 0);
      expect(tribe.subtitle, '0 people');
    });
  });

  group('Membership', () {
    test('pending is not active', () {
      // Pending grants nothing; the UI must never treat it as belonging.
      final membership = Membership.fromJson({
        'ulid': '01H',
        'status': 'pending',
        'scope': {'type': 'tribe', 'ulid': '01T', 'name': 'Zomi'},
      });

      expect(membership.isPending, isTrue);
      expect(membership.isActive, isFalse);
      expect(membership.scopeName, 'Zomi');
    });

    test('defaults to pending when the server omits a status', () {
      expect(Membership.fromJson({'ulid': '01H'}).isPending, isTrue);
    });
  });

  group('ProfileClaim', () {
    test('reads the decision and its note', () {
      final claim = ProfileClaim.fromJson({
        'ulid': '01H',
        'status': 'rejected',
        'decision_note': 'We could not confirm this.',
        'person': {'ulid': '01P', 'display_name': 'Thawng Dam'},
      });

      expect(claim.isRejected, isTrue);
      expect(claim.isPending, isFalse);
      expect(claim.personName, 'Thawng Dam');
      expect(claim.decisionNote, 'We could not confirm this.');
    });

    test('an approved claim is neither pending nor rejected', () {
      final claim = ProfileClaim.fromJson({'ulid': '01H', 'status': 'approved'});

      expect(claim.isApproved, isTrue);
      expect(claim.isPending, isFalse);
      expect(claim.isRejected, isFalse);
    });
  });
}
