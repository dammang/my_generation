#!/usr/bin/env bash
#
# Builds the web client and puts it on the server.
#
# Two things `flutter build web` will not do for you, both of which fail
# silently:
#
#   * Flutter 3.44 no longer copies arbitrary files out of web/ into the
#     build. sqlite3.wasm and drift_worker.js are downloaded assets, not
#     generated ones, so they have to be copied in by hand or the database
#     never opens and the app hangs on its splash screen.
#
#   * .htaccess is one of those files, and without it a browser keeps running
#     the previous build from cache.
#
set -euo pipefail

API="${API_BASE_URL:-https://khanggui.com}"
HOST="${DEPLOY_HOST:-root@khanggui.com}"
DEST="${DEPLOY_PATH:-/home/khanggui.com/public_html/public/app/}"

cd "$(dirname "$0")/.."

echo "==> building against $API"
flutter build web --base-href=/app/ --dart-define=API_BASE_URL="$API"

echo "==> copying the files flutter leaves behind"
for f in sqlite3.wasm drift_worker.js .htaccess; do
  cp "web/$f" "build/web/$f"
  printf '    %s\n' "$f"
done

# Neither main.dart.js nor flutter_bootstrap.js is content-hashed: a new build
# reuses the same names, so a browser holding the old ones keeps running the
# previous app. OpenLiteSpeed honours only rewrite directives in .htaccess, not
# Header, so the cache cannot be told to revalidate from here — the filename has
# to change instead.
#
# The stamp is the commit being deployed, so two builds of the same code produce
# the same URLs and a browser is not made to re-download 3.7MB for nothing.
STAMP="$(git rev-parse --short HEAD 2>/dev/null || date +%s)"

echo "==> stamping the bundle as $STAMP"
sed -i '' "s|flutter_bootstrap.js|flutter_bootstrap.js?v=$STAMP|g" build/web/index.html
sed -i '' "s|\"main.dart.js\"|\"main.dart.js?v=$STAMP\"|g" build/web/flutter_bootstrap.js

echo "==> uploading to $HOST:$DEST"
rsync -az --delete build/web/ "$HOST:$DEST"

echo "==> done. Check https://khanggui.com/app/"
