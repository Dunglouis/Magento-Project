#!/usr/bin/env bash
# CD watcher of a TechNest deploy environment, started in the background by up.sh.
# Every 5 minutes: deploy.sh (new commit on the branch?), and a daily DB backup.
# Usage: scripts/env/watch.sh <staging|production>   (log: ~/Sites/technest-<env>/logs/watch.log)
set -o errexit -o nounset -o pipefail

main() {
  # shellcheck source=lib.sh
  source "$(dirname "$0")/lib.sh"
  tn_env "${1:-}"
  local newest
  while true; do
    # The clone's own deploy.sh, so a deploy that changes it takes effect next round
    "$TN_DIR/scripts/env/deploy.sh" "$TN_ENV" || tn_log "deploy.sh failed"
    newest="$(find "$TN_DIR/backups" -name 'daily-*.sql.gz' -mmin -1440 2> /dev/null | head -1)"
    if [[ -z "$newest" ]]; then
      tn_log "Daily DB backup: $(tn_dump daily)" || tn_log "Daily DB backup failed"
    fi
    sleep 300
  done
}

main "$@"
exit
