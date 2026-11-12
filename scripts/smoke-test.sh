#!/usr/bin/env bash
# Smoke tests HTTP apres deploiement (Lot 6).
# Usage : scripts/smoke-test.sh [URL_DE_BASE]   (defaut : http://localhost:8080)
# Sortie : une ligne par page (code HTTP, duree) ; code de sortie 1 si une verification echoue.
set -uo pipefail

BASE_URL="${1:-http://localhost:8080}"
echec=0

verifier() { # $1 = chemin, $2 = code HTTP attendu
    local resultat code duree
    resultat=$(curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time 10 "${BASE_URL}$1")
    code=${resultat%% *}; duree=${resultat##* }
    if [ "$code" = "$2" ]; then
        printf 'OK    %-24s HTTP %s (attendu %s) %ss\n' "$1" "$code" "$2" "$duree"
    else
        printf 'ECHEC %-24s HTTP %s (attendu %s) %ss\n' "$1" "$code" "$2" "$duree"
        echec=1
    fi
}

# Sonde de sante : HTTP 200 ET corps {"status":"ok"} (verifie la connexion a MySQL).
corps=$(curl -s --max-time 10 "${BASE_URL}/health")
if [ "$corps" = '{"status":"ok"}' ]; then
    echo "OK    /health                  corps ${corps}"
else
    echo "ECHEC /health                  corps '${corps}'"
    echec=1
fi

verifier /health 200
verifier / 200
verifier /connexion 200
verifier /inscription 200
verifier /inscription/etape/1 200
verifier /reservation/mes-reservations 302   # page protegee : redirection vers /connexion
verifier /page-inexistante-smoke 404

if [ "$echec" -ne 0 ]; then
    echo "SMOKE TESTS : ECHEC"
    exit 1
fi
echo "SMOKE TESTS : OK"
