#!/usr/bin/env bash
#
# Builds the web client and puts it on the server, at the site root.
#
# Four things `flutter build web` will not do, each of which fails silently:
#
#   * Flutter 3.44 no longer copies arbitrary files out of web/. sqlite3.wasm
#     and drift_worker.js are downloaded assets, not generated ones, and
#     without them driftDatabase throws before the first frame and the app sits
#     on its splash screen.
#
#   * Neither main.dart.js nor flutter_bootstrap.js is content-hashed, so a new
#     build reuses the same names and browsers keep running the old app.
#     OpenLiteSpeed honours only rewrite directives in .htaccess — not Header —
#     so the cache cannot be told to revalidate; the URL has to change.
#
#   * The shell is deployed as app.html, not index.html. An index.html beside
#     Laravel's index.php makes which one answers "/" depend on the web
#     server's DirectoryIndex order. A route is the same everywhere.
#
#   * The destination is shared with Laravel. Two rules follow from that and
#     both are load-bearing:
#         no --delete, or index.php, build/ and storage/ go with it
#         never send .htaccess, or Laravel's rewrite rules are overwritten and
#         nothing on the site routes at all
#
set -euo pipefail

API="${API_BASE_URL:-https://khanggui.com}"
HOST="${DEPLOY_HOST:-root@khanggui.com}"
DEST="${DEPLOY_PATH:-/home/khanggui.com/public_html/public/}"

cd "$(dirname "$0")/.."

echo "==> building against $API"
flutter build web --base-href=/ --dart-define=API_BASE_URL="$API"

echo "==> copying the files flutter leaves behind"
for f in sqlite3.wasm drift_worker.js; do
  cp "web/$f" "build/web/$f"
  printf '    %s\n' "$f"
done

# The bundle's own contents, not the commit. A commit hash cannot see the
# working tree, so deploying an uncommitted change reused the previous stamp
# and every browser kept the bundle it already had — which is the exact failure
# the stamp exists to prevent, and it happened.
#
# A content hash changes when, and only when, the bundle does: rebuilding
# unchanged code does not force anybody to re-download 3.7MB.
STAMP="$(shasum -a 256 build/web/main.dart.js | cut -c1-12)"
echo "==> stamping the bundle as $STAMP"
sed -i '' "s|flutter_bootstrap.js|flutter_bootstrap.js?v=$STAMP|g" build/web/index.html
sed -i '' "s|\"main.dart.js\"|\"main.dart.js?v=$STAMP\"|g" build/web/flutter_bootstrap.js

echo "==> shell becomes app.html"
mv build/web/index.html build/web/app.html

echo "==> uploading to $HOST:$DEST"
# --delete is deliberately absent and .htaccess deliberately excluded; see above.
rsync -az --exclude '.htaccess' build/web/ "$HOST:$DEST"

echo "==> done. Check https://khanggui.com/"
