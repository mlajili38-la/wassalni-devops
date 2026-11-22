# Supervision, diagnostic, incident et rollback (Lot 7)

## 1. Journalisation : quoi, où, comment la consulter

Tous les journaux sont écrits sur la **sortie standard ou d'erreur des conteneurs**. Docker les collecte : il n'y a aucun fichier
de log à chercher dans les conteneurs, et `docker compose logs` regroupe en un seul endroit les journaux de tous les services.

| Source | Contenu | Format | Flux |
|---|---|---|---|
| Apache (accès) | une ligne par requête : IP, date, méthode, URL, **code HTTP**, taille, user-agent | *combined* | stdout du conteneur `app` |
| Apache (erreurs) | erreurs du serveur web et de PHP | texte | stderr du conteneur `app` |
| Symfony / Monolog | erreurs applicatives (niveau `error` et plus, avec les 50 messages qui précèdent), dont `Application health check failed.` avec l'exception | **JSON** (une ligne par événement) | stderr du conteneur `app` |
| Entrypoint | attente de MySQL, migrations, version démarrée (`[entrypoint] ...`) | texte | stdout du conteneur `app` |
| MySQL | démarrage, initialisation, erreurs | texte | conteneur `database` |

### Consulter les logs

| Contexte | Commande / emplacement |
|---|---|
| Local (Compose) | `docker compose --env-file .env.docker logs --follow --timestamps app database` |
| Local, erreurs seulement | `docker compose --env-file .env.docker logs app \| Select-String '"level_name":"(ERROR\|CRITICAL)"'` |
| Local, codes 5xx | `docker compose --env-file .env.docker logs app \| Select-String '" 5\d\d '` |
| GitHub Actions | onglet **Actions** → run → log de l'étape ; en cas d'échec du job `container`, l'étape *Show container logs on failure* affiche les logs |
| Archives | artefacts `deploy-test-<run_id>` (`app.log`, `database.log`, `containers.txt`) et `incident-drill-<run_id>` (`app-incident.log`, `app-final.log`, `monitoring.csv`) |

### Lire les logs (exemples réels de l'exercice d'incident)

```
# Monolog (JSON) : l'événement, la classe d'exception et le message SQL
{"message":"Application health check failed.","context":{"exception":{"class":"Doctrine\\DBAL\\Exception\\TableNotFoundException",
 "message":"... SQLSTATE[42S02]: Base table or view not found: 1146 Table 'wassalni.table_inexistante_incident' doesn't exist", ...

# Apache (accès) : la sonde reçoit 503
172.18.0.1 - - [12/Nov/2026:22:14:36 +0000] "GET /health HTTP/1.1" 503 279 "-" "curl/8.18.0"
```

Message MySQL à connaître : `root@localhost is created with an empty password ! Please consider switching off the --initialize-insecure option`
apparaît pendant **l'initialisation** du conteneur `mysql:8.0` (serveur temporaire). Le script d'initialisation de l'image applique
ensuite `MYSQL_ROOT_PASSWORD`. Ce n'est pas une faille de la configuration, mais il ne faut pas le confondre avec une erreur.

## 2. Sondes de santé

| Niveau | Mécanisme | Ce qui est vérifié | Utilisé par |
|---|---|---|---|
| Application | `GET /health` (`HealthController`) | Symfony répond **et** MySQL répond (`SELECT 1`) : `200 {"status":"ok","version":"<sha>"}` sinon `503 {"status":"unavailable","version":"<sha>"}` + log `error` (la version en service est visible même pendant un incident) | smoke tests, sonde de supervision |
| Conteneur `app` | `HEALTHCHECK` du Dockerfile et `healthcheck` Compose (`curl -fsS http://localhost/health`, toutes les 10 s, 5 essais, 60 s de démarrage) | même chose, du point de vue de Docker | `docker compose up --wait`, `docker compose ps` |
| Conteneur `database` | `mysqladmin ping` en TCP | MySQL accepte les connexions (pas pendant l'initialisation) | `depends_on: condition: service_healthy` |

Commandes : `docker compose ps` (colonne STATUS : `healthy` / `unhealthy` / `starting`),
`docker inspect --format '{{json .State.Health}}' <conteneur>` (historique des 5 derniers contrôles).

## 3. Indicateurs techniques

| Indicateur | Source | Valeur mesurée |
|---|---|---|
| État du service | `/health`, `docker compose ps` | `healthy` après chaque déploiement (runs *Deploy test*) |
| Temps de réponse | `scripts/smoke-test.sh` (`time_total` de curl), `monitoring.csv` | `/health` : 5 à 13 ms en moyenne ; pages : 7 à 120 ms (runner GitHub) |
| Disponibilité | `scripts/monitor-health.sh` (1 mesure/s) | 48,7 % sur la fenêtre de 39 s de l'exercice (incident volontaire) ; 100 % en dehors |
| Erreurs | logs Apache (codes 5xx), logs Monolog `ERROR` | 23 réponses `503` sur `/health` pendant l'incident, 0 après le rollback |
| MTTD (délai de détection) | exercice d'incident | **6 s** |
| MTTR (délai de restauration) | exercice d'incident | **24 s** depuis la détection (rollback : 8 s) |

Indicateurs de la chaîne (section 13 de l'énoncé) : voir `docs/preuves/lot7-incident-rollback.txt` § 4
(taux de réussite et durée des pipelines, calculés avec `gh run list`).

## 4. Procédure de diagnostic d'un incident

1. **Constater** : `/health` ≠ 200, `docker compose ps` affiche `unhealthy`, ou un smoke test échoue (pipeline rouge).
2. **Identifier la version en service** : `docker inspect --format '{{.Config.Image}}' $(docker compose ps -q app)` ;
   comparer avec la dernière version saine (résumé du run *Deploy test* ou `deployment-record.json`).
3. **Lire les logs depuis le début de l'incident** : `docker compose logs --since 10m app`
   - lignes JSON Monolog `"level_name":"ERROR"` : message et classe de l'exception ;
   - lignes Apache : URL et code HTTP concernés ;
   - lignes `[entrypoint]` : échec de connexion MySQL ou de migration au démarrage.
4. **Vérifier les dépendances** : `docker compose ps database`, `docker compose logs database`, `doctrine:migrations:up-to-date`.
5. **Décider** : si la dernière mise en service est en cause → **rollback** (§ 5) ; si c'est la configuration (secret, URL de base) → corriger la variable puis redéployer.

## 5. Procédure de rollback

Principe : chaque commit de `main` produit une image **immuable** dans GHCR, taguée par son SHA. Revenir en arrière consiste à
remettre en service l'image d'un SHA antérieur reconnu stable. Seul le conteneur `app` est remplacé : la base et son volume sont conservés.
Les migrations ne sont pas rejouées à l'envers, car aucune version publiée n'a de migration incompatible ; sinon, il faudrait exécuter
`doctrine:migrations:migrate <version>` avant le changement d'image.

| Où | Commande |
|---|---|
| GitHub Actions (démonstration) | *Actions* → **Incident drill and rollback** → *Run workflow* (version stable au choix) — ou `gh workflow run incident-rollback.yml -f stable_version=<SHA>` |
| GitHub Actions (redéployer une version) | *Actions* → **Deploy test** → *Run workflow* → `version=<SHA>` |
| Linux / runner | `scripts/rollback.sh <SHA>` (pull GHCR, `up --no-deps app`, migrations, `/health`, smoke tests, `rollback-record.json`) |
| Windows (Compose local) | `$env:DOCKER_IMAGE = "ghcr.io/mlajili38-la/wassalni-devops"` puis `.\scripts\rollback.ps1 -Version <SHA>` et `Invoke-WebRequest http://localhost:8080/health` (script d'origine, **non rejoué** pendant le Lot 7 ; la procédure démontrée est `scripts/rollback.sh`) |

Choisir la version stable : SHA de l'avant-dernier déploiement réussi (résumés *Deploy test*), ou liste des tags :
`gh api /users/<propriétaire>/packages/container/wassalni-devops/versions --jq '.[].metadata.container.tags'`.

## 6. Fiche d'incident — exercice du 12/11/2026 (GitHub Actions, run 43317764697)

| Champ | Valeur |
|---|---|
| Environnement | test, Docker Compose éphémère (`wassalni-test`) dans GitHub Actions |
| Version stable | `ghcr.io/mlajili38-la/wassalni-devops:7191382ddf67d7b9c6bfcaba70822166d24f33e2` |
| Version défectueuse | `…:incident-demo`, construite dans le job à partir de la version stable, **jamais publiée** ; le code du dépôt n'est pas modifié |
| Début | 22:14:30 UTC, mise en service de la version défectueuse |
| Détection | 22:14:36 UTC, sonde `/health` → `503 {"status":"unavailable"}` (**MTTD 6 s**) |
| Symptômes | `/health` en 503 ; smoke tests en échec ; conteneur `app` encore `starting` (période de démarrage de 60 s du healthcheck) ; les autres pages répondaient (200) |
| Diagnostic | 23 lignes Apache `GET /health ... 503` ; Monolog : `Application health check failed.` → `TableNotFoundException` : `SQLSTATE[42S02] ... Table 'wassalni.table_inexistante_incident' doesn't exist` ; image en service : `incident-demo` |
| Cause racine | la nouvelle version interroge une table inexistante dans sa sonde de santé (défaut injecté volontairement) |
| Action | `scripts/rollback.sh 7191382…` à 22:14:52 UTC : pull GHCR, remplacement du conteneur `app` seul |
| Retour à la normale | 22:15:00 UTC (rollback en **8 s**, **MTTR 24 s**) : `app` healthy, migrations à jour, `/health` 200, smoke tests 8/8 OK |
| Disponibilité sur la fenêtre | 19 mesures UP / 20 DOWN sur 39 (48,7 %) ; indisponibilité de 22:14:34 à 22:14:53 UTC. Les premières mesures DOWN sont en `000` (conteneur en cours de remplacement), puis en `503` |
| Actions de prévention | la règle de la CI empêche déjà de publier une image dont `/health` échoue (smoke tests du job `container`) ; pistes : déploiement *blue/green* pour ne remplacer l'ancienne version qu'après validation de la nouvelle |

Le même exercice a été exécuté en local (Docker Desktop) avec des résultats équivalents : MTTD 7 s, rollback 12 s, MTTR 30 s.
