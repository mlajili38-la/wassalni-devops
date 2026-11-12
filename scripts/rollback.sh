#!/usr/bin/env bash
# Rollback de l'environnement de test vers une version stable publiee dans GHCR (Lot 7).
#
# Usage : scripts/rollback.sh <VERSION_STABLE>   (SHA d'un commit de main dont l'image existe dans GHCR)
# Seul le conteneur app est remplace : la base MySQL et ses donnees (volume) sont conservees.
# Verifie ensuite : conteneur healthy, migrations, /health, smoke tests. Code de sortie 1 si echec.
set -euo pipefail
VERSION="${1:?Usage : scripts/rollback.sh <VERSION_STABLE>}"
: "${DOCKER_IMAGE:?DOCKER_IMAGE manquant}" "${APP_SECRET:?}" "${MYSQL_PASSWORD:?}" "${MYSQL_ROOT_PASSWORD:?}"
export APP_VERSION="$VERSION" DOCKER_IMAGE
export APP_PORT="${APP_PORT:-8080}" COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-wassalni-test}"
DEPLOY_DIR="${DEPLOY_DIR:-build/deploy}"; mkdir -p "$DEPLOY_DIR"
compose() { docker compose -f compose.yaml "$@"; }

echo "=== Rollback vers ${DOCKER_IMAGE}:${VERSION} ($(date -u +%H:%M:%S) UTC) ==="
docker pull -q "${DOCKER_IMAGE}:${VERSION}"
compose up -d --no-build --no-deps --wait --wait-timeout 180 app

echo "=== Verifications apres rollback ==="
compose ps --format 'table {{.Service}}\t{{.Image}}\t{{.Status}}'
compose exec -T app php bin/console doctrine:migrations:up-to-date --env=prod 2>&1 | grep -v '"channel":"deprecation"'
code=$(curl -s -o "${DEPLOY_DIR}/health-after-rollback.json" -w '%{http_code}' "http://localhost:${APP_PORT}/health")
echo "GET /health -> HTTP ${code} $(cat "${DEPLOY_DIR}/health-after-rollback.json")"
[ "$code" = "200" ] || { echo "ERREUR : le rollback n'a pas retabli le service"; exit 1; }
scripts/smoke-test.sh "http://localhost:${APP_PORT}"

cat > "${DEPLOY_DIR}/rollback-record.json" <<JSON
{
  "action": "rollback",
  "image": "${DOCKER_IMAGE}:${VERSION}",
  "digest": "$(docker image inspect --format '{{index .RepoDigests 0}}' "${DOCKER_IMAGE}:${VERSION}")",
  "done_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "result": "success"
}
JSON
echo "ROLLBACK OK : ${DOCKER_IMAGE}:${VERSION}"
