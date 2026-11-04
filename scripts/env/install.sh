#!/usr/bin/env bash
# First install of a TechNest deploy environment (CLAUDE.md section 10), run from the dev repo.
# Usage: scripts/env/install.sh <staging|production> [--branch <branch>]
#   --branch  clone another branch than the environment's own (to try a feature branch first)
#
# 1. Clones the repo into ~/Sites/technest-<env> and copies env/*.env of the dev repo
#    (Google redirect URI set to the environment URL, admin password reset to "changeme";
#    the Icecat username CONFIG__DEFAULT__TECHNEST_SEEDER__ICECAT__USERNAME must be in it),
#    then stops: set the admin account in ~/Sites/technest-<env>/env/magento.env and run again.
# 2. Starts the stack, composer install --no-dev, setup:install with the environment URL,
#    seeds the TechNest catalog, removes the sample data catalog, production mode.
# Safe to run again after a failure: finished steps are skipped or idempotent.
set -o errexit -o nounset -o pipefail

main() {
  # shellcheck source=lib.sh
  source "$(dirname "$0")/lib.sh"
  tn_env "${1:-}"
  local branch="$TN_BRANCH"
  if [[ "${2:-}" == --branch ]]; then
    branch="${3:?--branch needs a branch name}"
  fi
  local dev_dir
  dev_dir="$(cd "$TN_SCRIPTS/../.." && pwd)"

  if [[ ! -d "$TN_DIR/.git" ]]; then
    tn_log "Cloning $branch into $TN_DIR"
    git clone --branch "$branch" "$(git -C "$dev_dir" remote get-url origin)" "$TN_DIR"
  fi
  cd "$TN_DIR"

  if [[ ! -f env/db.env ]]; then
    tn_log "Copying env/*.env from $dev_dir"
    mkdir -p env
    cp "$dev_dir"/env/*.env env/
    sed -i "s|^CONFIG__DEFAULT__TECHNEST_GOOGLE__GENERAL__REDIRECT_URI=.*|CONFIG__DEFAULT__TECHNEST_GOOGLE__GENERAL__REDIRECT_URI=${TN_BASE_URL}technest_google/oauth/callback|" env/phpfpm.env
    sed -i 's/^MAGENTO_ADMIN_PASSWORD=.*/MAGENTO_ADMIN_PASSWORD=changeme/' env/magento.env
  fi
  if grep -q '^MAGENTO_ADMIN_PASSWORD=changeme$' env/magento.env; then
    tn_log "Set MAGENTO_ADMIN_EMAIL, MAGENTO_ADMIN_USER, MAGENTO_ADMIN_PASSWORD (and MAGENTO_ADMIN_FRONTNAME)"
    tn_log "in $TN_DIR/env/magento.env, then run: scripts/env/install.sh $TN_ENV"
    exit 2
  fi

  # DummyJSON/Icecat answers cached by the dev seeder: same products, no API calls
  if [[ -d "$dev_dir/src/var/technest_seed" && ! -d src/var/technest_seed ]]; then
    mkdir -p src/var
    cp -r "$dev_dir/src/var/technest_seed" src/var/
  fi

  tn_stop_others
  tn_log "Starting the stack"
  tn_compose up -d --wait

  tn_log "composer install --no-dev"
  tn_composer_install
  tn_nginx_conf

  # env.php gets its 'install' date only when setup:install has finished
  if ! grep -qs "'install'" src/app/etc/env.php; then
    tn_log "setup:install for $TN_BASE_URL"
    setup_install
  fi

  tn_log "Seeding the TechNest catalog and removing the sample data catalog"
  tn_magento indexer:set-mode schedule
  tn_magento technest:seed:run
  tn_magento technest:seed:cleanup-legacy --force

  tn_log "Production mode"
  tn_magento maintenance:enable
  set_front_end
  tn_magento deploy:mode:set production --skip-compilation
  tn_compile_static
  tn_magento indexer:reindex
  tn_magento cache:flush
  tn_magento maintenance:disable
  tn_health

  tn_log "Installed $(git rev-parse --short HEAD). Start it with: scripts/env/up.sh $TN_ENV"
}

setup_install() {
  # Values of the env files of the clone, read in a subshell so they stay out of this shell
  (
    # shellcheck disable=SC1091
    source env/db.env && source env/magento.env && source env/opensearch.env &&
      source env/rabbitmq.env && source env/redis.env
    tn_magento setup:install \
      --db-host="$MYSQL_HOST" \
      --db-name="$MYSQL_DATABASE" \
      --db-user="$MYSQL_USER" \
      --db-password="$MYSQL_PASSWORD" \
      --base-url="$TN_BASE_URL" \
      --base-url-secure="$TN_BASE_URL" \
      --use-secure=1 \
      --use-secure-admin=1 \
      --backend-frontname="$MAGENTO_ADMIN_FRONTNAME" \
      --admin-firstname="$MAGENTO_ADMIN_FIRST_NAME" \
      --admin-lastname="$MAGENTO_ADMIN_LAST_NAME" \
      --admin-email="$MAGENTO_ADMIN_EMAIL" \
      --admin-user="$MAGENTO_ADMIN_USER" \
      --admin-password="$MAGENTO_ADMIN_PASSWORD" \
      --language="$MAGENTO_LOCALE" \
      --currency="$MAGENTO_CURRENCY" \
      --timezone="$MAGENTO_TIMEZONE" \
      --amqp-host="$RABBITMQ_HOST" \
      --amqp-port="$RABBITMQ_PORT" \
      --amqp-user="$RABBITMQ_DEFAULT_USER" \
      --amqp-password="$RABBITMQ_DEFAULT_PASS" \
      --amqp-virtualhost="$RABBITMQ_DEFAULT_VHOST" \
      --cache-backend=redis \
      --cache-backend-redis-server="$REDIS_CACHE_BACKEND_SERVER" \
      --cache-backend-redis-db="$REDIS_CACHE_BACKEND_DB" \
      --page-cache=redis \
      --page-cache-redis-server="$REDIS_PAGE_CACHE_SERVER" \
      --page-cache-redis-db="$REDIS_PAGE_CACHE_DB" \
      --session-save=redis \
      --session-save-redis-host="$REDIS_SESSION_SAVE_HOST" \
      --session-save-redis-log-level=4 \
      --session-save-redis-db=2 \
      --opensearch-host="$OPENSEARCH_HOST" \
      --opensearch-port="$OPENSEARCH_PORT" \
      --search-engine=opensearch \
      --use-rewrites=1 \
      --no-interaction
  )
}

# Front-end optimisation in app/etc/env.php, as scripts/perf-mode.sh prod (no JS merge/bundling)
set_front_end() {
  local path
  for path in dev/template/minify_html dev/js/minify_files dev/css/minify_files dev/css/merge_css_files \
    dev/static/sign; do
    tn_magento config:set --lock-env "$path" 1
  done
  tn_magento config:set --lock-env dev/js/merge_files 0
  tn_magento config:set --lock-env dev/js/enable_js_bundling 0
}

main "$@"
exit
