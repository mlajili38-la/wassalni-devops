#!/usr/bin/env bash
# Sonde de supervision (Lot 7) : interroge /health toutes les secondes et ecrit une ligne CSV par mesure.
# Usage : scripts/monitor-health.sh <URL_DE_BASE> <FICHIER_CSV> [FICHIER_STOP]
# S'arrete quand FICHIER_STOP existe (defaut : <FICHIER_CSV>.stop).
# Colonnes : horodatage_utc,epoch,code_http,duree_s,statut   (statut = UP si HTTP 200, sinon DOWN)
set -u
BASE_URL="$1"; CSV="$2"; STOP="${3:-$2.stop}"
echo "horodatage_utc,epoch,code_http,duree_s,statut" > "$CSV"
while [ ! -f "$STOP" ]; do
    mesure=$(curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time 3 "${BASE_URL}/health" || true)
    code=${mesure%% *}; duree=${mesure##* }
    [ "$code" = "200" ] && statut=UP || statut=DOWN
    echo "$(date -u +%H:%M:%S),$(date +%s),${code:-000},${duree:-0},${statut}" >> "$CSV"
    sleep 1
done
