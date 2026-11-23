#!/usr/bin/env bash
#
# Server settings for this cPanel account. Used by .cpanel.yml ("Deploy HEAD Commit")
# and by the GitHub Actions SSH deploy, so both deploy exactly the same way.
#
# DEPLOYPATH = live app folder. The subdomain's document root must be DEPLOYPATH/public.
export DEPLOYPATH=/home/morethangiftz/smartai.morethangiftz.com

# PHP 8.3+ command-line binary. The plain `php` on cPanel is often an older default version,
# so look in the usual EasyApache and CloudLinux locations (newest first).
# To force a specific binary, set PHP_BIN here, e.g. export PHP_BIN=/opt/alt/php83/usr/bin/php
if [ -z "${PHP_BIN:-}" ]; then
    for candidate in \
        /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php \
        /opt/alt/php84/usr/bin/php /opt/alt/php83/usr/bin/php \
        /usr/local/bin/ea-php84 /usr/local/bin/ea-php83 \
        /usr/local/bin/php84 /usr/local/bin/php83 /usr/bin/php84 /usr/bin/php83 \
        "$(command -v php || true)"; do
        if [ -n "$candidate" ] && [ -x "$candidate" ] \
            && "$candidate" -d error_reporting=0 -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
            export PHP_BIN="$candidate"
            break
        fi
    done
fi

if [ -z "${PHP_BIN:-}" ]; then
    echo "ERROR: PHP 8.3 or newer was not found. Install it in cPanel -> MultiPHP Manager / Select PHP Version," >&2
    echo "then set PHP_BIN in deploy-cpanel.sh. PHP binaries found on this server:" >&2
    ls -1 /opt/cpanel/ea-php*/root/usr/bin/php /opt/alt/php*/usr/bin/php /usr/local/bin/ea-php* 2>/dev/null >&2 || true
    exit 1
fi

exec /bin/bash "$(dirname "$0")/deploy.sh"
