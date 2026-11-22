#!/usr/bin/env bash
# Exercice d'incident complet et reproductible (Lot 7).
#
# Usage : scripts/incident-drill.sh <VERSION_STABLE>
# Scenario :
#   1. deploiement de la version stable depuis GHCR (scripts/deploy-test.sh) ;
#   2. supervision continue de /health (scripts/monitor-health.sh, 1 mesure/s) ;
#   3. mise en service d'une version defectueuse (scripts/simulate-incident.sh) ;
#   4. detection de l'indisponibilite (sonde /health, smoke tests) ;
#   5. diagnostic a partir des logs du conteneur ;
#   6. rollback vers la version stable GHCR (scripts/rollback.sh) et verification ;
#   7. indicateurs (disponibilite, duree d'indisponibilite, MTTD, MTTR, erreurs 5xx) et fiche d'incident.
# Variables requises : DOCKER_IMAGE, APP_SECRET, MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD.
set -euo pipefail
STABLE="${1:?Usage : scripts/incident-drill.sh <VERSION_STABLE>}"
export APP_PORT="${APP_PORT:-8080}" COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-wassalni-test}"
export DEPLOY_DIR="${DEPLOY_DIR:-build/deploy}"
BASE_URL="http://localhost:${APP_PORT}"
CSV="${DEPLOY_DIR}/monitoring.csv"
mkdir -p "$DEPLOY_DIR"; rm -f "${CSV}.stop"
heure() { date -u +%H:%M:%S; }
compose() { APP_VERSION="${APP_VERSION:-$STABLE}" docker compose -f compose.yaml "$@"; }

echo "################ 1. Deploiement de la version stable ${STABLE} ################"
scripts/deploy-test.sh "$STABLE"

echo "################ 2. Demarrage de la supervision (/health chaque seconde) ################"
scripts/monitor-health.sh "$BASE_URL" "$CSV" &
MONITOR_PID=$!
trap 'touch "${CSV}.stop"; wait "$MONITOR_PID" 2>/dev/null || true' EXIT
sleep 5

echo "################ 3. Incident : mise en service d'une version defectueuse ################"
T0=$(date +%s); H0=$(heure)
scripts/simulate-incident.sh "$STABLE"

echo "################ 4. Detection de l'indisponibilite ################"
T_DETECT=""
for _ in $(seq 1 120); do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 3 "${BASE_URL}/health" || true)
    if [ "$code" != "200" ] && [ "$code" != "000" ]; then T_DETECT=$(date +%s); break; fi
    sleep 1
done
[ -n "$T_DETECT" ] || { echo "ERREUR : l'incident n'a pas ete detecte"; exit 1; }
H_DETECT=$(heure)
echo "Indisponibilite detectee a ${H_DETECT} UTC : GET /health -> HTTP ${code} $(curl -s --max-time 3 "${BASE_URL}/health")"
echo "Smoke tests pendant l'incident (echec attendu) :"
scripts/smoke-test.sh "$BASE_URL" > "${DEPLOY_DIR}/smoke-tests-incident.txt" 2>&1 && SMOKE_INCIDENT=OK || SMOKE_INCIDENT=ECHEC
cat "${DEPLOY_DIR}/smoke-tests-incident.txt"
sleep 15   # laisse le healthcheck Docker constater l'etat degrade
ETAT_DOCKER=$(docker inspect --format '{{.State.Health.Status}}' "$(compose ps -q app)")
echo "Etat Docker du conteneur app : ${ETAT_DOCKER}"

echo "################ 5. Diagnostic a partir des logs ################"
compose logs --no-color --timestamps app > "${DEPLOY_DIR}/app-incident.log" 2>&1 || true
IMAGE_EN_SERVICE=$(docker inspect --format '{{.Config.Image}}' "$(compose ps -q app)")
NB_503=$(grep -c '"GET /health HTTP/1.1" 503' "${DEPLOY_DIR}/app-incident.log" || true)
CAUSE=$(grep -o 'Application health check failed[^}]*' "${DEPLOY_DIR}/app-incident.log" | head -1 || true)
SQL=$(grep -o "SQLSTATE\[[^]]*\][^\"]*" "${DEPLOY_DIR}/app-incident.log" | head -1 || true)
echo "Image en service           : ${IMAGE_EN_SERVICE}"
echo "Requetes /health en 503    : ${NB_503}"
echo "Log applicatif (Monolog)   : ${CAUSE:0:120}"
echo "Erreur SQL                 : ${SQL}"

echo "################ 6. Rollback vers ${STABLE} ################"
T_RB=$(date +%s)
scripts/rollback.sh "$STABLE"
T_OK=$(date +%s); H_OK=$(heure)
sleep 5
touch "${CSV}.stop"; wait "$MONITOR_PID" 2>/dev/null || true; trap - EXIT

echo "################ 7. Indicateurs ################"
read -r TOTAL UP DOWN PREMIER_DOWN DERNIER_DOWN < <(awk -F, 'NR>1 { n++; if ($5=="UP") u++; else { d++; if (!f) f=$1; l=$1 } } END { printf "%d %d %d %s %s\n", n, u, d, (f?f:"-"), (l?l:"-") }' "$CSV")
DISPO=$(awk -v u="$UP" -v n="$TOTAL" 'BEGIN { printf "%.1f", (n ? 100*u/n : 0) }')
DUREE_MOY=$(awk -F, 'NR>1 && $5=="UP" { s+=$4; n++ } END { printf "%.3f", (n ? s/n : 0) }' "$CSV")
MTTD=$(( T_DETECT - T0 )); MTTR=$(( T_OK - T_DETECT )); DUREE_RB=$(( T_OK - T_RB ))
# Indicateurs repris par le tableau de bord (scripts/dashboard.py).
cat > "${DEPLOY_DIR}/metrics.env" <<ENV
MTTD=${MTTD}
MTTR=${MTTR}
DUREE_ROLLBACK=${DUREE_RB}
VERSION_STABLE=${STABLE}
VERSION_DEFECTUEUSE=incident-demo
ENV
cat > "${DEPLOY_DIR}/incident-report.md" <<MD
# Fiche d'incident (exercice automatise)

| Champ | Valeur |
|---|---|
| Environnement | test (Docker Compose, ${COMPOSE_PROJECT_NAME}) |
| Version stable | \`${DOCKER_IMAGE}:${STABLE}\` |
| Version defectueuse | \`${DOCKER_IMAGE}:incident-demo\` (construite localement, jamais publiee) |
| Debut de l'incident | ${H0} UTC |
| Detection | ${H_DETECT} UTC (MTTD : ${MTTD} s) par la sonde /health |
| Symptomes | /health -> HTTP 503 ; smoke tests : ${SMOKE_INCIDENT} ; etat Docker du conteneur app : ${ETAT_DOCKER} |
| Diagnostic (logs) | ${NB_503} requetes /health en 503 ; log Monolog : \`Application health check failed.\` ; cause : \`${SQL}\` |
| Cause racine | la version mise en service interroge une table inexistante dans la sonde de sante (defaut injecte) |
| Action | rollback vers la version stable GHCR (\`scripts/rollback.sh ${STABLE}\`), base de donnees conservee |
| Retour a la normale | ${H_OK} UTC (rollback : ${DUREE_RB} s ; MTTR depuis la detection : ${MTTR} s) |
| Verification | /health 200, migrations a jour, smoke tests OK |

## Indicateurs de supervision (sonde /health, 1 mesure/s)

| Indicateur | Valeur |
|---|---|
| Mesures | ${TOTAL} |
| Mesures UP / DOWN | ${UP} / ${DOWN} |
| Disponibilite sur la fenetre | ${DISPO} % |
| Indisponibilite observee | de ${PREMIER_DOWN} a ${DERNIER_DOWN} UTC |
| Temps de reponse moyen de /health (UP) | ${DUREE_MOY} s |
MD
cat "${DEPLOY_DIR}/incident-report.md"
echo "EXERCICE D'INCIDENT TERMINE : service retabli sur ${STABLE}"
