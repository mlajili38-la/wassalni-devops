#!/usr/bin/env bash
# Deploiement d'une image publiee dans GHCR vers l'environnement de test Docker Compose (Lot 6).
#
# Usage : scripts/deploy-test.sh <VERSION>   (tag GHCR : SHA de commit ou "latest")
# Variables requises (jamais ecrites dans le depot) :
#   DOCKER_IMAGE        ex. ghcr.io/mlajili38-la/wassalni-devops
#   APP_SECRET, MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD   (secrets GitHub TEST_*)
# Variables optionnelles : APP_PORT (8080), COMPOSE_PROJECT_NAME (wassalni-test), DEPLOY_DIR (build/deploy)
#
# Etapes : pull GHCR -> docker compose up -> etat des conteneurs -> migrations -> health check
#          -> smoke tests -> trace de la version deployee. Code de sortie 1 a la premiere erreur.
set -euo pipefail

VERSION="${1:?Usage : scripts/deploy-test.sh <VERSION>}"
: "${DOCKER_IMAGE:?DOCKER_IMAGE manquant}" "${APP_SECRET:?}" "${MYSQL_PASSWORD:?}" "${MYSQL_ROOT_PASSWORD:?}"
export APP_VERSION="$VERSION" DOCKER_IMAGE
export APP_PORT="${APP_PORT:-8080}"
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-wassalni-test}"
DEPLOY_DIR="${DEPLOY_DIR:-build/deploy}"
BASE_URL="http://localhost:${APP_PORT}"
compose() { docker compose -f compose.yaml "$@"; }
etape() { echo; echo "=== $* ==="; }

mkdir -p "$DEPLOY_DIR"

etape "1. Recuperation de l'image ${DOCKER_IMAGE}:${VERSION} depuis GHCR"
docker pull "${DOCKER_IMAGE}:${VERSION}"
DIGEST=$(docker image inspect --format '{{index .RepoDigests 0}}' "${DOCKER_IMAGE}:${VERSION}")
REVISION=$(docker image inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' "${DOCKER_IMAGE}:${VERSION}")
echo "digest   : ${DIGEST}"
echo "revision : ${REVISION}"

etape "2. Demarrage de l'environnement de test (Docker Compose, sans build)"
DEBUT=$(date +%s)
compose up -d --no-build --wait --wait-timeout 180
echo "environnement pret en $(( $(date +%s) - DEBUT ))s"

etape "3. Etat des conteneurs"
compose ps --format 'table {{.Service}}\t{{.Image}}\t{{.Status}}'
for service in app database; do
    etat=$(docker inspect --format '{{.State.Health.Status}}' "$(compose ps -q "$service")")
    echo "${service} : ${etat}"
    [ "$etat" = "healthy" ] || { echo "ERREUR : ${service} n'est pas healthy"; exit 1; }
done

etape "4. Migrations Doctrine"
# Les commandes Doctrine ecrivent leurs messages sur stderr : 2>&1, puis filtrage des lignes de deprecation.
compose exec -T app php bin/console doctrine:migrations:up-to-date --env=prod 2>&1 | grep -v '"channel":"deprecation"'
compose exec -T app php bin/console doctrine:schema:validate --skip-mapping --env=prod 2>&1 | grep -E '\[(OK|ERROR)\]'
compose exec -T app php bin/console doctrine:migrations:status --env=prod 2>&1 | grep -E 'Current|Latest|Executed|New' || true

etape "5. Sonde de sante (health check)"
code=$(curl -s -o "${DEPLOY_DIR}/health.json" -w '%{http_code}' "${BASE_URL}/health")
echo "GET /health -> HTTP ${code} $(cat "${DEPLOY_DIR}/health.json")"
[ "$code" = "200" ] || { echo "ERREUR : health check en echec"; exit 1; }
# Trace de la version : /health doit annoncer la revision de l'image deployee.
# Les images publiees avant l'ajout de ce champ ne l'exposent pas (cas d'un rollback ancien) : simple avertissement.
version_sante=$(sed -n 's/.*"version":"\([^"]*\)".*/\1/p' "${DEPLOY_DIR}/health.json")
if [ -z "$version_sante" ]; then
    echo "AVERTISSEMENT : /health n'expose pas de version (image anterieure a cette fonctionnalite)"
elif [ "$version_sante" != "$REVISION" ]; then
    echo "ERREUR : /health annonce la version ${version_sante}, l'image deployee est ${REVISION}"; exit 1
else
    echo "version en service : ${version_sante} (identique a la revision de l'image)"
fi

etape "6. Smoke tests"
scripts/smoke-test.sh "$BASE_URL" | tee "${DEPLOY_DIR}/smoke-tests.txt"

etape "7. Trace de la version deployee"
cat > "${DEPLOY_DIR}/deployment-record.json" <<JSON
{
  "environment": "test (Docker Compose ephemere, GitHub Actions)",
  "image": "${DOCKER_IMAGE}:${VERSION}",
  "digest": "${DIGEST}",
  "revision": "${REVISION}",
  "deployed_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "deployed_by": "${GITHUB_ACTOR:-local}",
  "workflow_run": "${GITHUB_SERVER_URL:-}/${GITHUB_REPOSITORY:-}/actions/runs/${GITHUB_RUN_ID:-local}",
  "health": $(cat "${DEPLOY_DIR}/health.json"),
  "result": "success"
}
JSON
cat "${DEPLOY_DIR}/deployment-record.json"
echo; echo "DEPLOIEMENT OK : ${DOCKER_IMAGE}:${VERSION}"
