#!/usr/bin/env bash
# Settings of the TechNest deploy environments (CLAUDE.md section 10).
# Usage: source scripts/env/lib.sh; tn_env <staging|production>
# Sets TN_ENV, TN_BRANCH, TN_DIR, TN_HTTPS_PORT, TN_BASE_URL and TN_FUNNEL (1 = public).

tn_env() {
  TN_ENV="$1"
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
