#!/usr/bin/env bash
# Static checks + unit tests for TechNest code, run inside the phpfpm container.
# Usage: scripts/qa.sh [--compile]
set -euo pipefail
cd "$(dirname "$0")/.."

QA=dev/tests/technest

echo "== PHPCS (Magento2)"
bin/clinotty vendor/bin/phpcs --standard=$QA/phpcs.xml

echo "== PHPStan"
bin/clinotty vendor/bin/phpstan analyse -c $QA/phpstan.neon --memory-limit=2G --no-progress

echo "== PHPUnit"
if compgen -G "src/app/code/TechNest/*/Test/Unit" > /dev/null; then
    bin/clinotty sh -c 'cd dev/tests/unit && ../../../vendor/bin/phpunit ../../../app/code/TechNest'
else
    echo "(no unit tests yet, skipped)"
fi

if [[ "${1:-}" == "--compile" ]]; then
    echo "== setup:di:compile"
    bin/clinotty bin/magento setup:di:compile
fi
