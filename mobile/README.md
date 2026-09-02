# My Generation — mobile

Flutter client for the My Generation genealogy archive.

## Running

The API must be reachable. From the repository root:

```bash
php artisan serve
```

Then:

```bash
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000
```

`API_BASE_URL` is optional on a simulator — the default resolves per platform,
because "localhost" is not one address:

| Target | Reaches the host at |
|---|---|
| iOS simulator | `127.0.0.1` |
| Android emulator | `10.0.2.2` |
| Physical device | The Mac's LAN address — must be passed explicitly |

For a physical device, serve on all interfaces (`php artisan serve --host=0.0.0.0`)
and pass your machine's address.

## Tests

```bash
flutter test                                                    # unit tests
flutter test --dart-define=LIVE_API=true test/live_api_contract_test.dart
```

Contract tests are gated behind the define so a plain `flutter test` never
depends on a server being up, and they skip themselves if it is unreachable
anyway. They sign in once and reuse the token — the auth endpoints are throttled
to five attempts a minute per address, and a suite that signs in per test fails
on the platform's own protection.

The contract tests check the client's parsing against a **real** server response.
Unit tests prove the models handle the JSON we believe the API sends; these prove
they handle what it actually sends, and the two diverge the moment a field is
renamed on one side.

## Development shortcuts

Debug builds only — both are gated on `kDebugMode`, not on the value being
absent, so a release build carrying one still ignores it.

```bash
flutter run \
  --dart-define=DEV_TOKEN="$TOKEN" \   # start already signed in
  --dart-define=DEV_ROUTE=/tree         # open straight onto a screen
```

Useful on a simulator, where typing credentials on every reload is slow and
automated checks cannot type at all.

## The tree

The layout engine is a **pure function** of the graph — no widgets, no state, no
framework. That is deliberate: layout is the part most likely to be wrong in a
way nobody notices, since a sibling drawn under the wrong couple still looks
like a family tree. Being pure is what makes it testable without a screen, and
the contract tests lay out a real server response and assert no two cards
overlap.

Three passes: rows by depth (the server assigns it), ordering within each row by
the median position of neighbours in the adjacent row with couples kept
together, then coordinates with each parent pulled to sit centred over their own
children.

Connectors are painted; people are real widgets positioned over the canvas. A
painted card cannot be tapped, focused, read by a screen reader or animated, and
a family tree whose people are inert pictures is a diagram rather than an
interface. Only nodes inside the viewport plus a margin are built.

## Structure

```
lib/
  config/        compile-time environment (--dart-define)
  core/
    constants/   API paths, in one place
    errors/      ApiException — every failure the UI can act on
    network/     Dio client, auth interceptor, envelope parsing
    theme/       large type, quiet palette, both light and dark
  models/        API-shaped models
  database/      Drift: the local mirror and the sync queue
  services/      secure storage (Keychain / Android KeyStore)
  repositories/  the one place API and local database are reconciled
  providers/     Riverpod wiring
  routing/       GoRouter, driven by auth state
  features/      one folder per screen area
  l10n/          .arb files; no screen hardcodes a user-facing string
```

## Conventions

- **The server decides what can be seen.** `redacted` and `placeholder` arrive
  from the API already applied. The client renders them; it never decides them,
  and never asks for something it intends to hide.
- **Dates are rendered as sent.** "abt. 1902" is evidence — the client shows the
  source's wording rather than reformatting a date.
- **Warnings are not errors.** A write can succeed *and* carry doubt; the UI
  surfaces `warnings[]` without treating the write as failed.
- The token lives in the platform keystore, never in the database or
  SharedPreferences, and the local cache is wiped on sign-out.
