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

## Releasing

`API_BASE_URL` is **not optional** here. It defaults to empty, and
`ApiConfig.defaultBaseUrl` then falls back to loopback — `127.0.0.1:8000` on
iOS, `10.0.2.2:8000` on Android. On a phone that is the phone's own loopback,
so a release build without the define installs cleanly, launches cleanly, and
fails every request with "Cannot reach My Generation". Nothing about the build
says anything is wrong.

```bash
flutter build ios --release --dart-define=API_BASE_URL=https://khanggui.com
```

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://khanggui.com
```

To put it on a connected device (`flutter devices` for the id — Dam's iPhone
pairs wirelessly):

```bash
flutter install --release -d <device-id>
```

Check the flag actually took, rather than trusting that you typed it. The
define is compiled into the Dart snapshot, so it is visible in the binary:

```bash
strings build/ios/iphoneos/Runner.app/Frameworks/App.framework/App | grep -oE 'https?://[a-z0-9.:]+' | sort -u
```

Framework documentation URLs (`pub.dev`, `api.flutter.dev`) come back too and
are noise. What matters is that the production host is present and neither
loopback address is:

```
https://api.flutter.dev
https://docs.flutter.dev
https://khanggui.com      <- the one that matters
https://pub.dev
```

`flutter install` **uninstalls the old copy first**, and iOS removes app data
with the app: the signed-in session, the offline archive, and anything still
waiting in the Outbox all go. Warn whoever holds the phone that they will be
signing in again and re-granting notification permission.

## The web client

Served from `https://khanggui.com/app/`, on the same host as the API on
purpose. A browser build talking to an API on another origin needs CORS
configured, every request preflighted, and an allowed-origin list kept in step
with wherever it is hosted; same-origin means there is nothing to configure.
`CORS_ALLOWED_ORIGINS` stays empty.

```bash
API_BASE_URL=https://khanggui.com tool/deploy_web.sh
```

Use the script rather than `flutter build web`. Three things it does that the
build does not, each of which fails silently:

**It copies `sqlite3.wasm` and `drift_worker.js` into the output.** Flutter 3.44
no longer copies arbitrary files out of `web/`, and these are downloaded assets
rather than generated ones. Without them `driftDatabase` throws before the first
frame and the app sits on its splash screen forever. They come from the releases
matching the pinned versions in `pubspec.lock`:

| File | Source |
|---|---|
| `sqlite3.wasm` | `simolus3/sqlite3.dart` release `sqlite3-<version>` |
| `drift_worker.js` | `simolus3/drift` release `drift-<version>` |

Re-download both when either package is upgraded; a worker built against a
different sqlite3 is not a combination anybody has tested.

**It stamps the bundle URLs with the commit.** Neither `main.dart.js` nor
`flutter_bootstrap.js` is content-hashed, so a new build reuses the same names
and a browser keeps running the old app. OpenLiteSpeed honours only rewrite
directives in `.htaccess` — not `Header` — so the cache cannot be told to
revalidate and the filename has to change instead. The stamp is the commit, so
rebuilding the same code does not force a 3.7MB re-download.

**It uploads.** The build is 42MB of generated output and is never committed.

### What differs from the phone

Crashlytics has no web implementation at all, so it is skipped there — touching
the instance throws, and it used to take the whole app down before the first
frame. Push notifications need a service worker and a VAPID key and are not set
up. Sign in with Apple is iOS-only and the button does not appear.

Google sign-in needs `khanggui.com` under **Firebase → Authentication →
Settings → Authorised domains**. Email and password work without it.

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
