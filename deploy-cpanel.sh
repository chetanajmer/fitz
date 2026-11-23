#!/usr/bin/env bash
#
# Server settings for this cPanel account. Used by .cpanel.yml ("Deploy HEAD Commit")
# and by the GitHub Actions SSH deploy, so both deploy exactly the same way.
#
# DEPLOYPATH = live app folder. The subdomain's document root must be DEPLOYPATH/public.
export DEPLOYPATH=/home/morethangiftz/smartai.morethangiftz.com
export PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php

exec /bin/bash "$(dirname "$0")/deploy.sh"
