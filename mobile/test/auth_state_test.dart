import 'package:flutter_test/flutter_test.dart';
import 'package:my_generation/models/api_user.dart';
import 'package:my_generation/providers/auth_provider.dart';

ApiUser _user({List<int> tribeIds = const [], String? personUlid}) => ApiUser(
      ulid: '01U',
      name: 'Dam Mang',
      email: 'dam@example.com',
      locale: 'en',
      status: 'active',
      emailVerified: true,
      isSuperAdmin: false,
      permissions: const ['people.view'],
      tribeIds: tribeIds,
      clanIds: const [],
      branchIds: const [],
      personUlid: personUlid,
    );

void main() {
  group('AuthState', () {
    test('unknown is distinct from signed out', () {
      // The distinction matters: "we have not checked yet" must not send
      // somebody who is signed in to the sign-in screen.
      expect(const AuthUnknown(), isA<AuthState>());
      expect(const AuthSignedOut(), isNot(isA<AuthUnknown>()));
    });

    test('a signed-out state can explain itself', () {
      const state = AuthSignedOut(message: 'Your session has ended.');

      expect(state.message, 'Your session has ended.');
    });

    test('signed in carries the user', () {
      final state = AuthSignedIn(_user(tribeIds: const [1]));

      expect(state.user.tribeIds, [1]);
    });
  });

  group('onboarding need', () {
    test('a member of a tribe has nothing left to join', () {
      expect(_user(tribeIds: const [1]).tribeIds, isNotEmpty);
    });

    test('an account with no memberships has not joined anything', () {
      expect(_user().tribeIds, isEmpty);
    });

    test('claiming is separate from belonging', () {
      // A member need not have claimed a person, and a claimant need not stop
      // being asked to join anything else.
      final member = _user(tribeIds: const [1]);
      final claimant = _user(personUlid: '01P');

      expect(member.hasClaimedPerson, isFalse);
      expect(claimant.hasClaimedPerson, isTrue);
      expect(claimant.tribeIds, isEmpty);
    });
  });
}
