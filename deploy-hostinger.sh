#!/usr/bin/env bash
#
# Hostinger deploy. GitHub Actions uploads the ready-to-run build (vendor/ and public/build/ included)
# to a temporary folder on the server and runs this script from there; deploy.sh then copies it into
# the live app folder and runs the Laravel steps (migrate, storage link, caches).
#
#   DEPLOYPATH  live app folder, e.g. /home/u123456789/domains/fitnix.in/laravel
#               The domain's public_html must be a link to DEPLOYPATH/public (see the one-time setup).
#   PHP_BIN     optional: a specific PHP 8.3+ binary; otherwise found below.
set -euo pipefail

export DEPLOYPATH="${DEPLOYPATH:?Set DEPLOYPATH (GitHub secret HOSTINGER_APP_PATH) to the live app folder}"

# Hostinger runs CloudLinux: every PHP version is under /opt/alt. The plain `php` may be an older default.
if [ -z "${PHP_BIN:-}" ]; then
    for candidate in /opt/alt/php83/usr/bin/php /usr/bin/php83 /opt/alt/php84/usr/bin/php /usr/bin/php84 "$(command -v php || true)"; do
        if [ -n "$candidate" ] && [ -x "$candidate" ] \
            && "$candidate" -d error_reporting=0 -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
            export PHP_BIN="$candidate"
            break
        fi
    done
fi

if [ -z "${PHP_BIN:-}" ]; then
    echo "ERROR: PHP 8.3 or newer was not found. Choose PHP 8.3 in hPanel -> Advanced -> PHP Configuration." >&2
    ls -1 /opt/alt/php*/usr/bin/php 2>/dev/null >&2 || true
    exit 1
fi

exec /bin/bash "$(dirname "$0")/deploy.sh"
