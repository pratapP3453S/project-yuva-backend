#!/usr/bin/env bash
#
# Bring up the Docker Compose stack (Linux / server / macOS).
#
# Usage:
#   ./rundocker.sh dev          # local development (+ phpMyAdmin)
#   ./rundocker.sh stg          # staging
#   ./rundocker.sh prod         # production
#
#   ./rundocker.sh dev down     # stop & remove the dev stack
#   ./rundocker.sh prod logs    # tail logs for the prod stack
#
# An explicit environment is REQUIRED — there is intentionally no default,
# so nobody can accidentally launch prod when they meant dev.
#
set -euo pipefail

cd "$(dirname "$0")"

ENV="${1:-}"
ACTION="${2:-up}"

if [ -z "$ENV" ]; then
  echo "Usage: $0 <dev|stg|prod> [up|down|logs|ps]" >&2
  exit 1
fi

case "$ENV" in
  dev|stg|prod) ;;
  *) echo "Unknown environment '$ENV' (expected: dev | stg | prod)" >&2; exit 1 ;;
esac

OVERRIDE_FILE="docker-compose.${ENV}.yml"

if [ ! -f "$OVERRIDE_FILE" ]; then
  echo "Override file '$OVERRIDE_FILE' not found." >&2
  exit 1
fi

if [ ! -f ".env" ]; then
  echo "Missing .env file. Copy .env.example to .env and fill in the values first:" >&2
  echo "    cp .env.example .env" >&2
  exit 1
fi

COMPOSE=(docker compose -f docker-compose.yml -f "$OVERRIDE_FILE")

case "$ACTION" in
  up)
    echo "Starting '$ENV' stack..."
    "${COMPOSE[@]}" up -d
    ;;
  down)
    echo "Stopping '$ENV' stack..."
    "${COMPOSE[@]}" down
    ;;
  logs)
    "${COMPOSE[@]}" logs -f --tail=100
    ;;
  ps)
    "${COMPOSE[@]}" ps
    ;;
  *)
    echo "Unknown action '$ACTION' (expected: up | down | logs | ps)" >&2
    exit 1
    ;;
esac
