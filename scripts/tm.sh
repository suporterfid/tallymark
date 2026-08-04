#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

COMPOSE=(docker compose)
COMPOSE_FILES=(-f compose.yaml)

if [[ "${TM_CI:-}" == "1" || "${CI:-}" == "true" || "${GITHUB_ACTIONS:-}" == "true" ]]; then
  COMPOSE_FILES+=(-f compose.ci.yaml)
fi

compose() {
  "${COMPOSE[@]}" "${COMPOSE_FILES[@]}" "$@"
}

warn_packagist_mirror() {
  if [[ -n "${COMPOSER_PACKAGIST_URL:-}" ]]; then
    echo "WARNING: COMPOSER_PACKAGIST_URL is set (${COMPOSER_PACKAGIST_URL}). Custom Packagist mirrors can cause stale or incomplete installs." >&2
  fi
}

composer_env_args() {
  local args=()
  if [[ -n "${COMPOSER_PACKAGIST_URL:-}" ]]; then
    args+=(-e "COMPOSER_PACKAGIST_URL=${COMPOSER_PACKAGIST_URL}")
  fi
  if (( ${#args[@]} > 0 )); then
    printf '%s\n' "${args[@]}"
  fi
}

composer_install_with_retry() {
  local attempt=1
  local delay=5
  local max_attempts=3

  warn_packagist_mirror
  mapfile -t env_args < <(composer_env_args)

  while (( attempt <= max_attempts )); do
    if compose run --rm "${env_args[@]}" app composer install "$@"; then
      return 0
    fi

    if (( attempt == max_attempts )); then
      echo "Composer failed after ${max_attempts} attempts." >&2
      return 1
    fi

    echo "Composer attempt ${attempt} failed; retrying in ${delay}s..." >&2
    sleep "$delay"
    delay=$((delay * 2))
    attempt=$((attempt + 1))
  done
}

composer_audit_with_retry() {
  local attempt=1
  local delay=5
  local max_attempts=3

  while (( attempt <= max_attempts )); do
    if compose run --rm app composer audit --locked --no-interaction --no-cache; then
      return 0
    fi

    if (( attempt == max_attempts )); then
      echo "Composer security audit could not be verified after ${max_attempts} attempts." >&2
      return 1
    fi

    echo "Composer security audit attempt ${attempt} failed; retrying in ${delay}s..." >&2
    sleep "$delay"
    delay=$((delay * 2))
    attempt=$((attempt + 1))
  done
}

usage() {
  cat <<'EOF'
TallyMark Docker toolchain

Usage:
  ./scripts/tm.sh <verb> [args...]

Verbs:
  up           Start app, mysql, mailpit, and demosite
  down         Stop and remove containers
  bootstrap    Install dependencies, prepare env, and migrate database
  composer     Run Composer via the app container
  artisan      Run Artisan via the app container
  npm          Run npm via the node container (dev profile)
  test         Run the PHPUnit suite
  e2e          Run the frontend end-to-end suite
  load         Run the synthetic ingest load fixture
  release      Build the production release zip
  deploy       Build and deploy the production release
  shell        Open a shell in the app container
  help         Show this help
EOF
}

cmd_up() {
  compose up -d --build mysql mailpit demosite app
}

cmd_down() {
  compose down "$@"
}

cmd_bootstrap() {
  if [[ ! -f .env ]]; then
    cp .env.example .env
    echo "Created .env from .env.example"
  fi

  compose up -d --build mysql mailpit demosite
  compose up -d --wait mysql
  composer_install_with_retry
  compose run --rm app php artisan key:generate --force
  compose run --rm app php artisan migrate --force

  if [[ -f frontend/package.json ]]; then
    compose --profile dev run --rm node npm --prefix frontend ci
  fi

  compose up -d --build app
  echo "Bootstrap complete."
}

cmd_composer() {
  if [[ "${1:-}" == "install" ]]; then
    composer_install_with_retry "${@:2}"
    return
  fi

  warn_packagist_mirror
  mapfile -t env_args < <(composer_env_args)
  compose run --rm "${env_args[@]}" app composer "$@"
}

cmd_artisan() {
  compose run --rm app php artisan "$@"
}

cmd_npm() {
  compose --profile dev run --rm --service-ports node npm "$@"
}

cmd_test() {
  compose run --rm app php artisan test "$@"
}

cmd_e2e() {
  if [[ ! -f frontend/package.json ]]; then
    echo "The frontend E2E suite is introduced in PR16." >&2
    return 1
  fi

  compose --profile dev run --rm --service-ports node npm --prefix frontend run e2e -- "$@"
}

cmd_load() {
  if [[ ! -f tests/Support/GeneratesBufferFixtures.php ]]; then
    echo "The synthetic load fixture is introduced in PR15." >&2
    return 1
  fi

  compose run --rm app php artisan analytics:load "$@"
}

cmd_release() {
  compose run --rm app php scripts/license-audit.php
  composer_audit_with_retry

  if [[ ! -f docker/release/Dockerfile ]]; then
    echo "The release pipeline is introduced in PR14." >&2
    return 1
  fi

  mkdir -p dist
  docker build -f docker/release/Dockerfile --target export --output "type=local,dest=./dist" .
  bash "$ROOT_DIR/scripts/validate-release.sh" "$ROOT_DIR/dist"
}

cmd_deploy() {
  if [[ ! -f scripts/deploy.sh ]]; then
    echo "Deployment tooling is introduced in PR14." >&2
    return 1
  fi

  cmd_release
  bash "$ROOT_DIR/scripts/deploy.sh" "$@"
}

cmd_shell() {
  compose run --rm app bash
}

main() {
  local verb="${1:-help}"
  shift || true

  case "$verb" in
    up) cmd_up "$@" ;;
    down) cmd_down "$@" ;;
    bootstrap) cmd_bootstrap "$@" ;;
    composer) cmd_composer "$@" ;;
    artisan) cmd_artisan "$@" ;;
    npm) cmd_npm "$@" ;;
    test) cmd_test "$@" ;;
    e2e) cmd_e2e "$@" ;;
    load) cmd_load "$@" ;;
    release) cmd_release "$@" ;;
    deploy) cmd_deploy "$@" ;;
    shell) cmd_shell "$@" ;;
    help|-h|--help) usage ;;
    *)
      echo "Unknown verb: $verb" >&2
      usage >&2
      return 1
      ;;
  esac
}

main "$@"
