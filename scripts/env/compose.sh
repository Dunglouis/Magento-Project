#!/usr/bin/env bash
# docker compose for a TechNest deploy environment (project technest-<env>, files of its clone).
# Usage: scripts/env/compose.sh <staging|production> <compose args>
#   e.g. scripts/env/compose.sh staging ps
set -o errexit
# shellcheck source=lib.sh
source "$(dirname "$0")/lib.sh"
tn_env "$1"
shift
tn_compose "$@"
