#!/usr/bin/env bash
# Refresh the local (developer mode) store after code changes: theme static files + Magento caches.
# Removing deployed_version.txt gives static URLs a new version, so the browser does not keep old CSS/JS.
# Usage: scripts/refresh.sh [--upgrade]   (--upgrade also runs setup:upgrade: new module, schema or data patch)
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ "${1:-}" == "--upgrade" ]]; then
    bin/magento setup:upgrade
fi

bin/clinotty rm -rf \
    pub/static/frontend/TechNest \
    var/view_preprocessed/pub/static/frontend/TechNest \
    pub/static/deployed_version.txt
bin/magento cache:flush

echo "Done. In the browser: Ctrl+Shift+R; if customer data (name, cart) still looks old, sign out and in again."
