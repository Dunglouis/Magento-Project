#!/usr/bin/env bash
# docker compose for a TechNest deploy environment, run in its clone (TN_DIR).
# Usage: scripts/env/compose.sh <staging|production> <compose args>
#   e.g. scripts/env/compose.sh staging ps
set -o errexit
# shellcheck source=lib.sh
source "$(dirname "$0")/lib.sh"
tn_env "$1"
shift
cd "$TN_DIR"

exec docker compose \
  -f compose.yaml \
  -f compose.healthcheck.yaml \
  -f compose.override.yaml \
  -f compose.deploy.yaml \
  "$@"
