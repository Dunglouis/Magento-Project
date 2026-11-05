#!/usr/bin/env bash
# Pull-based CD of a TechNest deploy environment: deploy the newest commit of its branch.
# Usage: scripts/env/deploy.sh <staging|production> [--force]   (run by watch.sh every 5 minutes)
#   --force  rebuild even when the clone is already on the newest commit
#
# backup DB -> maintenance -> checkout -> composer install --no-dev -> compile -> static
# -> setup:upgrade --keep-generated -> cache flush -> health check.
# On failure: back to the previous commit with its DB.
# The body is one function, parsed before it runs, so the checkout may rewrite this file.
set -o errexit -o nounset -o pipefail

main() {
  # shellcheck source=lib.sh
  source "$(dirname "$0")/lib.sh"
  tn_env "${1:-}"
  local force="${2:-}"
  cd "$TN_DIR"
  mkdir -p backups

  exec 9> backups/deploy.lock
  if ! flock -n 9; then
    tn_log "Another deploy is running"
    return 0
  fi

  git fetch --quiet origin "$TN_BRANCH"
  local current target dump
  current="$(git rev-parse HEAD)"
  target="$(git rev-parse "origin/$TN_BRANCH")"
  if [[ "$current" == "$target" && "$force" != --force ]]; then
    return 0
  fi

  tn_log "Deploying $TN_BRANCH ${current:0:7} -> ${target:0:7}"
  dump="$(tn_dump deploy "${current:0:7}")"
  tn_log "DB backup: $dump"

  if release "$target" upgrade && tn_health; then
    tn_log "Deployed ${target:0:7}"
    return 0
  fi

  tn_log "Deploy of ${target:0:7} failed, rolling back to ${current:0:7}"
  if tn_maintenance_on && tn_restore "$dump" && release "$current" && tn_health; then
    tn_log "Rolled back to ${current:0:7}"
  else
    tn_log "Rollback failed: the store stays in maintenance mode, see this log"
  fi
  return 1
}

# Code of <commit> into the clone and the container; "upgrade" also runs setup:upgrade.
# Cron is stopped meanwhile: its indexer and queue consumer processes compete for the RAM of the build.
release() {
  local commit="$1" upgrade="${2:-}"
  tn_maintenance_on &&
    { tn_root service cron stop || true; } &&
    git checkout --quiet --force -B "$TN_BRANCH" "$commit" &&
    tn_composer_install &&
    tn_nginx_conf &&
    tn_compile_static &&
    if [[ "$upgrade" == upgrade ]]; then tn_magento setup:upgrade --keep-generated; fi &&
    tn_magento cache:flush &&
    tn_magento maintenance:disable &&
    tn_root service cron start
}

main "$@"
exit
