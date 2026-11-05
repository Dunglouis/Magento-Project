#!/usr/bin/env bash
# Start a TechNest deploy environment: stops the dev stack and the other environment (RAM),
# starts the stack and cron, opens Tailscale Funnel (production) and the CD watcher.
# Usage: scripts/env/up.sh <staging|production>   (dev again later: cd ~/Sites/magento && bin/start)
set -o errexit -o nounset -o pipefail

main() {
  # shellcheck source=lib.sh
  source "$(dirname "$0")/lib.sh"
  tn_env "${1:-}"
  if [[ ! -f "$TN_DIR/src/app/etc/env.php" ]]; then
    echo "$TN_ENV is not installed yet: scripts/env/install.sh $TN_ENV" >&2
    exit 1
  fi
  cd "$TN_DIR"
  mkdir -p backups logs

  tn_stop_others
  tn_log "Starting the stack"
  # Recreated: after a Windows restart Docker Desktop starts the containers before the WSL disk
  # is mounted, so their src/ bind mount is empty
  tn_compose up -d --force-recreate --wait

  # The crontab lives in the container, so it is lost when the container is recreated
  tn_root service cron start
  tn_magento cron:install --force

  if [[ "$TN_FUNNEL" == 1 ]]; then
    tn_log "Opening Tailscale Funnel ${TN_BASE_URL} -> localhost:$TN_HTTPS_PORT"
    "$TN_TAILSCALE" funnel --bg "https+insecure://localhost:$TN_HTTPS_PORT"
  fi

  local pid
  pid="$(tn_watch_pid)"
  if [[ -n "$pid" ]]; then
    tn_log "CD watcher already running (pid $pid)"
  else
    nohup setsid "$TN_DIR/scripts/env/watch.sh" "$TN_ENV" >> logs/watch.log 2>&1 < /dev/null &
    echo $! > "$(tn_watch_pid_file)"
    tn_log "CD watcher started (pid $!, log $TN_DIR/logs/watch.log)"
  fi

  tn_health || true
  tn_log "Up: $TN_BASE_URL"
}

main "$@"
exit
