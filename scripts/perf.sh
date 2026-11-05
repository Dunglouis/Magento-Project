#!/usr/bin/env bash
# k6 load test (tests/perf/checkout.js) through the grafana/k6 Docker image; nothing to install on the host.
# - Perf customers perf-NN@technest.test share a password kept in env/perf.env (created on first run, not committed).
# - Orders are real COD orders: the script picks accessories with enough salable stock.
# - Email sending is switched off during the run (system/smtp/disable) so no mail goes out through SMTP.
# - Cron is stopped during the run (see below) and started again at the end, as is email sending.
# Usage: scripts/perf.sh <label> [k6 options]   e.g. scripts/perf.sh before-developer --vus 10
# Result: tests/perf/results/<label>.json (k6 summary export)
set -euo pipefail
cd "$(dirname "$0")/.."

LABEL="${1:?Usage: scripts/perf.sh <label> [k6 options]}"
shift
BASE_URL="${BASE_URL:-https://technest.test}"
VUS="${VUS:-20}"

if [[ ! -f env/perf.env ]]; then
    echo "TECHNEST_PERF_PASSWORD=$(openssl rand -hex 12)Aa1" > env/perf.env
    echo "Created env/perf.env"
fi
# shellcheck disable=SC1091
source env/perf.env

# Accessories with at least 20 salable units (source qty minus open order reservations), most first
PRODUCTS=$(bin/mysql -N -e "
    SELECT CONCAT(e.sku, '|', u.request_path)
    FROM catalog_product_entity e
    JOIN cataloginventory_stock_item s ON s.product_id = e.entity_id
    JOIN url_rewrite u ON u.entity_id = e.entity_id AND u.entity_type = 'product'
        AND u.store_id = 1 AND u.metadata IS NULL AND u.redirect_type = 0
    LEFT JOIN (SELECT sku, SUM(quantity) AS reserved FROM inventory_reservation GROUP BY sku) r ON r.sku = e.sku
    WHERE e.type_id = 'simple' AND e.sku LIKE 'TN-AC-%' AND s.is_in_stock = 1
        AND s.qty + COALESCE(r.reserved, 0) >= 20
    ORDER BY s.qty + COALESCE(r.reserved, 0) DESC
    LIMIT 10" | paste -sd, -)
[[ -n "$PRODUCTS" ]] || { echo "No accessory with enough stock"; exit 1; }

restore() {
    bin/magento config:set system/smtp/disable 0 > /dev/null
    bin/cron start > /dev/null
    echo "Email sending and cron switched back on"
}
bin/magento config:set system/smtp/disable 1 > /dev/null
trap restore EXIT

# Measure without cron: on the 6 GB dev machine its jobs (one PHP process per group every minute) swap and
# dominate the timings. Wait for running jobs and queue consumers to finish (at most 5 minutes).
bin/cron stop > /dev/null
for _ in $(seq 60); do
    bin/clinotty pgrep -f "bin/magento (cron:run|queue:consumers:start)" > /dev/null || break
    sleep 5
done
echo "Cron stopped, load: $(cut -d' ' -f1-3 /proc/loadavg)"

mkdir -p tests/perf/results
docker run --rm -i --user "$(id -u):$(id -g)" \
    --add-host technest.test:host-gateway \
    -v "$PWD/tests/perf:/scripts" \
    -e BASE_URL="$BASE_URL" -e VUS="$VUS" -e PRODUCTS="$PRODUCTS" \
    -e PERF_PASSWORD="$TECHNEST_PERF_PASSWORD" \
    grafana/k6 run --quiet --summary-export "/scripts/results/$LABEL.json" "$@" /scripts/checkout.js
