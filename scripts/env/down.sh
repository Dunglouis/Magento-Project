#!/usr/bin/env bash
# Stop a TechNest deploy environment: CD watcher, Tailscale Funnel (production) and the stack.
# Usage: scripts/env/down.sh <staging|production>
set -o errexit -o nounset -o pipefail

main() {
  # shellcheck source=lib.sh
  source "$(dirname "$0")/lib.sh"
  tn_env "${1:-}"

  local pid
  pid="$(tn_watch_pid)"
  if [[ -n "$pid" ]]; then
    # Waits for a running deploy: deploy.sh holds backups/deploy.lock
    flock "$TN_DIR/backups/deploy.lock" kill -- "-$pid" 2> /dev/null || kill "$pid" || true
    tn_log "CD watcher stopped"
  fi
  rm -f "$(tn_watch_pid_file)"

  if [[ "$TN_FUNNEL" == 1 ]]; then
    "$TN_TAILSCALE" funnel reset || true
    tn_log "Tailscale Funnel closed"
  fi

  tn_compose stop
  tn_log "Down"
}

main "$@"
exit
