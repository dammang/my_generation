import 'dart:math';

/// Client-side ULID generation.
///
/// The public identifier is minted on the device so a person created offline
/// can be referred to before the server has ever seen them. ULIDs are unique by
/// construction — 80 bits of randomness under a millisecond timestamp — so two
/// phones inventing one at the same moment do not collide, and the server needs
/// no id-mapping table when the queue finally arrives.
class Ulid {
  const Ulid._();

  /// Crockford base32: no I, L, O or U, so a transcribed id cannot be misread.
  static const _alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

  static final _random = Random.secure();

  static String generate([DateTime? at]) {
    final time = (at ?? DateTime.now()).millisecondsSinceEpoch;
    final buffer = StringBuffer();

    // 48 bits of timestamp, most significant first, so ids sort by creation
    // time — which is what keeps them indexing well on the server.
    for (var shift = 45; shift >= 0; shift -= 5) {
      buffer.write(_alphabet[(time >> shift) & 0x1F]);
    }

    for (var i = 0; i < 16; i++) {
      buffer.write(_alphabet[_random.nextInt(32)]);
    }

    return buffer.toString();
  }
}
