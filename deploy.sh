#!/usr/bin/env bash
#
# cPanel deploy: copies the checked-out repository into the live app folder and runs the
# Laravel deploy steps there. Started through deploy-cpanel.sh, either by cPanel's
# "Deploy HEAD Commit" (.cpanel.yml) or by the GitHub Actions SSH deploy.
#
# The repository must be the "deploy" branch built by GitHub Actions: it already contains
# vendor/ (Composer packages) and public/build/ (compiled CSS/JS), so the server needs no
# Composer or Node.
#
#   DEPLOYPATH  live app folder (the subdomain's document root is DEPLOYPATH/public)
#   PHP_BIN     PHP 8.3 command-line binary
#   PHP_WEB_HANDLER  optional .htaccess block that selects the website PHP version (kept on every deploy)
set -euo pipefail

REPO="$(cd "$(dirname "$0")" && pwd)"
DEPLOYPATH="${DEPLOYPATH:?Set DEPLOYPATH to the live app folder}"
PHP_BIN="${PHP_BIN:-php}"

PHP_VERSION="$($PHP_BIN -d error_reporting=0 -r 'echo PHP_VERSION;' 2>/dev/null)"
echo "==> Deploying $REPO -> $DEPLOYPATH (PHP $PHP_VERSION at $PHP_BIN)"

if ! $PHP_BIN -d error_reporting=0 -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
    echo "ERROR: PHP 8.3 or newer is required, but $PHP_BIN is PHP $PHP_VERSION. Set PHP_BIN in deploy-cpanel.sh." >&2
    exit 1
fi

if [ ! -f "$REPO/vendor/autoload.php" ] || [ ! -f "$REPO/public/build/manifest.json" ]; then
    echo "ERROR: vendor/ or public/build/ is missing. cPanel must track the 'deploy' branch built by GitHub Actions." >&2
    exit 1
fi

mkdir -p "$DEPLOYPATH"
if [ ! -f "$DEPLOYPATH/.env" ]; then
    echo "ERROR: $DEPLOYPATH/.env is missing. Create it once by hand (it is never deployed from git)." >&2
    exit 1
fi

# Maintenance mode while files change (skipped on the very first deploy).
if [ -f "$DEPLOYPATH/artisan" ]; then
    $PHP_BIN "$DEPLOYPATH/artisan" down --retry=15 || true
fi
trap '$PHP_BIN "$DEPLOYPATH/artisan" up >/dev/null 2>&1 || true' EXIT

# Keep the PHP version chosen in cPanel (MultiPHP Manager / .htaccess handler) across deploys:
# the repository's public/.htaccess would otherwise overwrite it and the site would fall back to old PHP.
PHP_HANDLER=""
if [ -f "$DEPLOYPATH/public/.htaccess" ]; then
    # 1) cPanel MultiPHP block, 2) a <FilesMatch> block that sets a PHP handler (kept whole: a bare
    # SetHandler would send every file, CSS and images included, to PHP), 3) plain AddHandler lines.
    PHP_HANDLER="$(awk '
        /# php -- BEGIN cPanel-generated handler/ { inside = 1 }
        inside { print; if (/# php -- END cPanel-generated handler/) inside = 0; next }
        /<FilesMatch/ { block = $0; collecting = 1; found = 0; next }
        collecting {
            block = block "\n" $0
            if (tolower($0) ~ /(sethandler|addhandler).*(php)/) found = 1
            if ($0 ~ /<\/FilesMatch>/) { if (found) print block; collecting = 0 }
            next
        }
        tolower($0) ~ /^[[:space:]]*addhandler[[:space:]].*php/ { print }
    ' "$DEPLOYPATH/public/.htaccess")"
fi

echo "==> Copying application files"
/usr/bin/rsync -a \
    --exclude='.git/' --exclude='.github/' --exclude='.cpanel.yml' \
    --exclude='.env' --exclude='.env.*' --exclude='node_modules/' --exclude='tests/' \
    --exclude='storage/' --exclude='public/storage' --exclude='bootstrap/cache/*.php' \
    --exclude='vendor/' --exclude='public/build/' \
    "$REPO/" "$DEPLOYPATH/"

# Packages and compiled assets are replaced exactly (old files removed).
/usr/bin/rsync -a --delete "$REPO/vendor/" "$DEPLOYPATH/vendor/"
/usr/bin/rsync -a --delete "$REPO/public/build/" "$DEPLOYPATH/public/build/"

# The handler set in deploy-cpanel.sh wins; otherwise the one found in the live file is kept.
if [ -n "${PHP_WEB_HANDLER:-}" ]; then
    PHP_HANDLER="$PHP_WEB_HANDLER"
fi

if [ -n "$PHP_HANDLER" ] && ! grep -qF "$(printf '%s' "$PHP_HANDLER" | head -n 1)" "$DEPLOYPATH/public/.htaccess"; then
    printf '\n%s\n' "$PHP_HANDLER" >> "$DEPLOYPATH/public/.htaccess"
    echo "==> Kept the cPanel PHP version setting in public/.htaccess"
fi

echo "==> Preparing storage (uploads, logs and sessions are kept between deploys)"
mkdir -p "$DEPLOYPATH"/storage/app/public "$DEPLOYPATH"/storage/framework/{cache/data,sessions,views} \
    "$DEPLOYPATH"/storage/logs "$DEPLOYPATH"/bootstrap/cache

echo "==> Setting permissions"
/usr/bin/find "$DEPLOYPATH" -path "$DEPLOYPATH/storage" -prune -o -type d -exec chmod 755 {} +
/usr/bin/find "$DEPLOYPATH" -path "$DEPLOYPATH/storage" -prune -o -type f ! -name '.env' -exec chmod 644 {} +
/bin/chmod -R 775 "$DEPLOYPATH/storage" "$DEPLOYPATH/bootstrap/cache"

cd "$DEPLOYPATH"

echo "==> Running database migrations"
$PHP_BIN artisan migrate --force

echo "==> Linking storage (uploaded photos)"
[ -L public/storage ] || $PHP_BIN artisan storage:link

echo "==> Rebuilding caches"
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan optimize

echo "==> Deploy finished"
