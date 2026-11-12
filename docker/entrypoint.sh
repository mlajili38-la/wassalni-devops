#!/bin/sh
# Point d'entree du conteneur applicatif WASSALNI.
# 1. Attend que MySQL accepte les connexions (DATABASE_URL fournie au runtime).
# 2. Applique les migrations Doctrine (idempotent : ne rejoue que les nouvelles).
# 3. Lance Apache au premier plan.
set -e

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    echo "[entrypoint] Attente de la base de donnees..."
    tries=0
    until php bin/console dbal:run-sql "SELECT 1" --env=prod >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "[entrypoint] ERREUR : base de donnees injoignable apres 30 tentatives." >&2
            exit 1
        fi
        sleep 2
    done

    echo "[entrypoint] Application des migrations Doctrine..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod
fi

# Le cache genere par root doit rester modifiable par Apache (www-data).
chown -R www-data:www-data var

echo "[entrypoint] Demarrage d'Apache (version ${APP_VERSION:-inconnue})."
exec "$@"
