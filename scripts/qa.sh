#!/usr/bin/env bash
# Static checks + unit tests for TechNest code.
# Local: runs inside the phpfpm container. CI: QA_NATIVE=1 runs directly in src/.
# Usage: scripts/qa.sh [--compile]
set -euo pipefail
cd "$(dirname "$0")/.."

QA=dev/tests/technest

if [[ "${QA_NATIVE:-}" == "1" ]]; then
    cd src
    # Mirror the container mount of tests/qa (copy only, never delete: $QA may be a mount)
    mkdir -p "$QA" && cp ../tests/qa/* "$QA"/
    run() { "$@"; }
    CODE=app/code/TechNest
else
    run() { bin/clinotty "$@"; }
    CODE=src/app/code/TechNest
fi

# Compile first: PHPStan needs the full generated extension interfaces (generated/code)
if [[ "${1:-}" == "--compile" ]]; then
    echo "== setup:di:compile"
    run bin/magento setup:di:compile
    # Local only: compiled metadata would freeze DI in the dev container (new classes/plugins ignored).
    # Keep generated/code, which PHPStan needs.
    if [[ "${QA_NATIVE:-}" != "1" ]]; then
        run rm -rf generated/metadata
    fi
fi

echo "== PHPCS (Magento2)"
run vendor/bin/phpcs --standard=$QA/phpcs.xml

echo "== PHPStan"
run vendor/bin/phpstan analyse -c $QA/phpstan.neon --memory-limit=2G --no-progress

echo "== PHPUnit"
if compgen -G "$CODE/*/Test/Unit" > /dev/null; then
    run sh -c 'cd dev/tests/unit && ../../../vendor/bin/phpunit ../../../app/code/TechNest'
else
    echo "(no unit tests yet, skipped)"
fi
