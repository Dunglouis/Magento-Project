#!/usr/bin/env bash
# Settings and helpers of the TechNest deploy environments (CLAUDE.md section 10).
# Usage: source scripts/env/lib.sh; tn_env <staging|production>; tn_magento cache:flush
# tn_env sets TN_ENV, TN_BRANCH, TN_DIR, TN_HTTPS_PORT, TN_BASE_URL and TN_FUNNEL (1 = public).

TN_SCRIPTS="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TN_DEV_PROJECT=magento
# shellcheck disable=SC2034 # used by up.sh and down.sh
TN_TAILSCALE="/mnt/c/Program Files/Tailscale/tailscale.exe"
TN_LOCALES=(en_US vi_VN)
TN_THEMES=(--theme TechNest/minimal --theme Magento/backend)

tn_env() {
  TN_ENV="${1:-}"
  case "$TN_ENV" in
    staging)
      TN_BRANCH=staging
      TN_HTTPS_PORT=9443
      TN_BASE_URL=https://localhost:9443/
      TN_FUNNEL=0
      ;;
    production)
      TN_BRANCH=main
      TN_HTTPS_PORT=8443
      TN_BASE_URL=https://technest.gourami-pirate.ts.net/
      TN_FUNNEL=1
      ;;
    *)
      echo "Unknown environment '$TN_ENV', use staging or production." >&2
      return 1
      ;;
  esac
  TN_DIR="$HOME/Sites/technest-$TN_ENV"
  export TN_ENV TN_BRANCH TN_DIR TN_HTTPS_PORT TN_BASE_URL TN_FUNNEL
}

tn_log() {
  echo "[$(date '+%F %T')] [$TN_ENV] $*"
}

tn_compose() {
  docker compose --project-directory "$TN_DIR" \
    -f "$TN_DIR/compose.yaml" \
    -f "$TN_DIR/compose.healthcheck.yaml" \
    -f "$TN_DIR/compose.override.yaml" \
    -f "$TN_DIR/compose.deploy.yaml" \
    "$@"
}

# Commands in the PHP container, as the app user (tn_cli) or root (tn_root)
tn_cli() {
  tn_compose exec -T phpfpm "$@"
}

tn_root() {
  tn_compose exec -T -u root phpfpm "$@"
}

tn_magento() {
  tn_cli bin/magento "$@"
}

tn_composer_install() {
  tn_cli composer install --no-dev --no-interaction --no-progress
}

# src/nginx.conf from Magento's sample, routed through the upstream map of the nginx image
# (same rewrite as bin/setup-nginx), then reloaded
tn_nginx_conf() {
  # shellcheck disable=SC2016 # $fastcgi_backend is the nginx variable
  sed -E 's|fastcgi_pass[[:space:]]+fastcgi_backend;|fastcgi_pass $fastcgi_backend;|g' \
    "$TN_DIR/src/nginx.conf.sample" > "$TN_DIR/src/nginx.conf" &&
    tn_compose exec -T app nginx -s reload
}

# Compiled DI and static files for production mode (same themes/locales as scripts/perf-mode.sh),
# then the optimized class map, built only now so it never lists generated files deleted later
tn_compile_static() {
  tn_cli rm -rf generated/code generated/metadata var/view_preprocessed pub/static/frontend \
    pub/static/adminhtml pub/static/deployed_version.txt &&
    tn_magento setup:di:compile &&
    tn_magento setup:static-content:deploy -f --jobs 1 "${TN_THEMES[@]}" "${TN_LOCALES[@]}" &&
    tn_cli composer dump-autoload --no-dev --optimize
}

# Gzipped mysqldump into $TN_DIR/backups/<kind>-<date>[-<label>].sql.gz, keeps the 7 newest
# of each kind and prints the file name.
tn_dump() {
  local kind="$1" label="${2:-}" file
  mkdir -p "$TN_DIR/backups"
  file="$TN_DIR/backups/$kind-$(date +%Y%m%d-%H%M%S)${label:+-$label}.sql.gz"
  # shellcheck disable=SC2016 # expanded inside the db container
  tn_compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mariadb-dump -u "$MYSQL_USER" \
    --single-transaction --no-tablespaces "$MYSQL_DATABASE"' | gzip > "$file"
  # shellcheck disable=SC2012 # backup names have no spaces
  ls -1t "$TN_DIR/backups/$kind-"*.sql.gz | tail -n +8 | xargs -r rm --
  echo "$file"
}

# Replace the whole database with a tn_dump file
tn_restore() {
  local file="$1"
  # shellcheck disable=SC2016 # expanded inside the db container
  tn_compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb -u root -e \
    "DROP DATABASE IF EXISTS \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\`; \
     GRANT ALL ON \`$MYSQL_DATABASE\`.* TO \`$MYSQL_USER\`@\`%\`;"' &&
    gunzip -c "$file" | tn_compose exec -T db sh -c \
      'MYSQL_PWD="$MYSQL_PASSWORD" mariadb -u "$MYSQL_USER" "$MYSQL_DATABASE"'
}

# Home page and login page answer 200 with the TechNest brand, asked with the public host
tn_health() {
  local host="${TN_BASE_URL#https://}" path code body
  host="${host%/}"
  body="$(mktemp)"
  for path in "" "customer/account/login/"; do
    code="$(curl -k -s -o "$body" -w '%{http_code}' --max-time 120 -H "Host: $host" \
      "https://127.0.0.1:$TN_HTTPS_PORT/$path")" || code=000
    if [[ "$code" != 200 ]] || ! grep -q TechNest "$body"; then
      tn_log "Health check failed: /$path answered $code"
      rm -f "$body"
      return 1
    fi
  done
  rm -f "$body"
  tn_log "Health check passed"
}

# Free the RAM: stop the dev stack and the other deploy environment (other Compose projects are left alone)
tn_stop_others() {
  local project
  for project in $(docker compose ls -q); do
    case "$project" in
      "technest-$TN_ENV") ;;
      technest-staging | technest-production)
        "$TN_SCRIPTS/down.sh" "${project#technest-}"
        ;;
      "$TN_DEV_PROJECT")
        tn_log "Stopping the dev stack ($project)"
        docker compose -p "$project" stop
        ;;
      *)
        tn_log "Note: Compose project '$project' is running too and uses RAM"
        ;;
    esac
  done
}

tn_watch_pid_file() {
  echo "$TN_DIR/backups/watch.pid"
}
