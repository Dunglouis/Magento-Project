#!/usr/bin/env bash
# Switch the local store between developer mode (theme/code work) and production mode (demo, performance test).
# Front-end optimisation settings are written to app/etc/env.php (--lock-env) so they never reach developer
# mode by accident and are not stored in the database.
# Usage: scripts/perf-mode.sh prod [--bundle]   production mode + minify/merge (+ RequireJS bundling)
#        scripts/perf-mode.sh dev               back to developer mode, optimisations off
set -euo pipefail
cd "$(dirname "$0")/.."

MODE="${1:?Usage: scripts/perf-mode.sh prod [--bundle] | dev}"
BUNDLE=0
[[ "${2:-}" == "--bundle" ]] && BUNDLE=1

LOCALES=(en_US vi_VN)
THEMES=(--theme TechNest/minimal --theme Magento/backend)

# Generated code, compiled DI and static files of the previous mode
clean() {
    bin/clinotty rm -rf generated/code generated/metadata var/view_preprocessed pub/static/frontend \
        pub/static/adminhtml pub/static/deployed_version.txt
}

set_front_end() {
    local on="$1" bundle="$2"
    bin/magento config:set --lock-env dev/template/minify_html "$on"
    bin/magento config:set --lock-env dev/js/minify_files "$on"
    bin/magento config:set --lock-env dev/css/minify_files "$on"
    bin/magento config:set --lock-env dev/css/merge_css_files "$on"
    # Merging JS fights RequireJS (it loads modules on demand); bundling is the measured alternative
    bin/magento config:set --lock-env dev/js/merge_files 0
    bin/magento config:set --lock-env dev/js/enable_js_bundling "$bundle"
    bin/magento config:set --lock-env dev/static/sign 1
}

case "$MODE" in
    prod)
        bin/magento maintenance:enable
        set_front_end 1 "$BUNDLE"
        bin/magento deploy:mode:set production --skip-compilation
        clean
        bin/magento setup:di:compile
        bin/magento setup:static-content:deploy -f --jobs 1 "${THEMES[@]}" "${LOCALES[@]}"
        bin/magento cache:flush
        bin/magento maintenance:disable
        ;;
    dev)
        set_front_end 0 0
        bin/magento deploy:mode:set developer
        clean
        bin/magento cache:flush
        ;;
    *)
        echo "Unknown mode: $MODE (prod|dev)"
        exit 1
        ;;
esac
bin/magento deploy:mode:show
