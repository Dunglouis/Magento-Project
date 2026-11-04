#!/usr/bin/env bash
# docker compose for the TechNest demo stack (project "technest-demo").
# Usage: scripts/demo/compose.sh <compose args>, e.g. scripts/demo/compose.sh ps
# Run it from the demo clone (~/Sites/technest-demo); see compose.demo.yaml.
set -o errexit
cd "$(dirname "$0")/../.."

exec docker compose \
  -f compose.yaml \
  -f compose.healthcheck.yaml \
  -f compose.override.yaml \
  -f compose.demo.yaml \
  "$@"
