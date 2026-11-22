#!/usr/bin/env bash
# Simulation d'un incident controle (Lot 7) : mise en service d'une VERSION DEFECTUEUSE.
#
# Usage : scripts/simulate-incident.sh <VERSION_DE_BASE>
# Construit LOCALEMENT (jamais publiee) l'image <DOCKER_IMAGE>:incident-demo a partir de <VERSION_DE_BASE>
# en y injectant un defaut : la sonde /health interroge une table inexistante -> HTTP 503.
# Le code du depot n'est PAS modifie : le defaut n'existe que dans cette image jetable.
# Puis remplace le conteneur app de l'environnement de test par cette version.
set -euo pipefail
BASE="${1:?Usage : scripts/simulate-incident.sh <VERSION_DE_BASE>}"
: "${DOCKER_IMAGE:?DOCKER_IMAGE manquant}"
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-wassalni-test}"

echo "=== Construction de la version defectueuse ${DOCKER_IMAGE}:incident-demo (base ${BASE}) ==="
docker build -q -t "${DOCKER_IMAGE}:incident-demo" - <<DOCKERFILE
FROM ${DOCKER_IMAGE}:${BASE}
LABEL org.opencontainers.image.revision="incident-demo"
ENV APP_VERSION="incident-demo"
RUN sed -i "s/executeQuery('SELECT 1')/executeQuery('SELECT 1 FROM table_inexistante_incident')/" src/Controller/HealthController.php \
 && grep -q table_inexistante_incident src/Controller/HealthController.php
DOCKERFILE

echo "=== Mise en service de la version defectueuse ($(date -u +%H:%M:%S) UTC) ==="
APP_VERSION=incident-demo docker compose -f compose.yaml up -d --no-build --no-deps app
