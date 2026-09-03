#!/usr/bin/env bash
#
# Deploys khanggui.com on the CyberPanel / OpenLiteSpeed server.
#
# CyberPanel has no deploy-script UI the way Forge does, so this lives in the
# repository and is run on the server:
#
#     bash /home/khanggui.com/public_html/deploy.sh
#
# Safe to re-run. It does nothing clever: the value is that the steps which are
# easy to forget — building assets, restarting the worker — cannot be forgotten.
set -euo pipefail

SITE="${SITE:-/home/khanggui.com/public_html}"
# OpenLiteSpeed's PHP, not the system one. `php` on the PATH is often a
# different build with different extensions, and using it here is how a deploy
# succeeds while the site keeps running something else.
PHP="${PHP:-/usr/local/lsws/lsphp84/bin/php}"
BRANCH="${BRANCH:-master}"

cd "$SITE"

echo "==> PHP: $("$PHP" -v | head -1)"

echo "==> Pulling $BRANCH"
git pull origin "$BRANCH"

echo "==> Composer"
"$PHP" "$(command -v composer)" install \
    --no-dev --no-interaction --prefer-dist --optimize-autoloader

# The password-reset and verification pages are served by this app, and
# public/build is gitignored. Without this every visit to them throws
# "Unable to locate file in Vite manifest".
echo "==> Building assets"
npm ci
npm run build

echo "==> Migrating"
"$PHP" artisan migrate --force

echo "==> Caching config, routes, views, events"
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache

# The running worker holds the previous release's code until told otherwise.
echo "==> Restarting the queue worker"
"$PHP" artisan queue:restart

# OpenLiteSpeed runs PHP as the site user; storage and the compiled-view cache
# are the only paths the application itself writes to.
#
# Owner and group are read off the site directory rather than named here.
# CyberPanel sets them and does not set the same thing everywhere — this box is
# khang6168:nogroup, a RHEL one would say nobody — and copying what is already
# there cannot be wrong in the way hardcoding a guess can.
echo "==> Permissions"
chown -R "$(stat -c '%U:%G' "$SITE")" storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "==> Reloading OpenLiteSpeed"
/usr/local/lsws/bin/lswsctrl restart

echo "==> Done."
