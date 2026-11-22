# Déploiement en environnement de test (Lot 6)

## 1. Environnements

| | Développement | Test |
|---|---|---|
| Où | Poste du développeur (WAMP, `symfony serve`) | Runner GitHub Actions (Linux), stack Docker Compose |
| Application | code source local, `APP_ENV=dev` | **image publiée dans GHCR**, `APP_ENV=prod` |
| Base | MySQL WAMP (`wassalni`) | conteneur `mysql:8.0` dédié, volume propre |
| Secrets | `.env.local`, `.env.dev.local` (non versionnés) | secrets GitHub `TEST_APP_SECRET`, `TEST_MYSQL_PASSWORD`, `TEST_MYSQL_ROOT_PASSWORD` |
| Durée de vie | permanente | **éphémère** : créée puis détruite à chaque déploiement (choix du projet, aucun serveur ni coût) |

## 2. Chaîne de déploiement

```
push sur main ──► CI (quality + security + container) ──► image ghcr.io/<dépôt>:<sha> + :latest
                                                              │
                     workflow « Deploy test » (workflow_run) ◄┘   ou déclenchement manuel (version au choix)
                                                              │
   docker pull (GHCR) ─► compose up ─► conteneurs healthy ─► migrations ─► /health ─► smoke tests ─► trace de version
```

Workflow : `.github/workflows/deploy-test.yml`. Script : `scripts/deploy-test.sh` (identique en local et en CI).

| Déclencheur | Version déployée | Rôle |
|---|---|---|
| `workflow_run` après une CI **verte** sur `main` | SHA du commit qui vient d'être publié | déploiement continu en test |
| `workflow_dispatch` (bouton **Run workflow**) | tag GHCR saisi (vide = `latest`) | **approbation manuelle** / redéploiement d'une version précise |
| `pull_request` modifiant le déploiement | `latest` | validation du workflow avant fusion |

Approbation manuelle : les environnements GitHub avec relecteurs obligatoires (*required reviewers*) ne sont pas disponibles
sur un dépôt **privé** avec un compte gratuit. Le déclenchement manuel `workflow_dispatch` joue ce rôle : une personne choisit
explicitement la version à déployer. Avec GitHub Pro, il suffirait d'ajouter `environment: test` au job et un relecteur à l'environnement.

## 3. Étapes de `scripts/deploy-test.sh` (entrées, sorties, conditions d'échec)

| # | Étape | Entrée | Sortie / preuve | Échoue si… |
|---|---|---|---|---|
| 1 | Récupération de l'image | `DOCKER_IMAGE`, `VERSION` | digest `sha256:…`, label `revision` | l'image n'existe pas dans GHCR ou l'accès est refusé |
| 2 | Démarrage Compose (`--no-build --wait`) | `compose.yaml`, secrets | durée de démarrage | un service n'est pas `healthy` en 180 s |
| 3 | État des conteneurs | `docker inspect` | tableau `app` / `database` | un conteneur n'est pas `healthy` |
| 4 | Migrations Doctrine | conteneur `app` | `Up-to-date`, `schema in sync`, version courante | migration manquante ou schéma désynchronisé |
| 5 | Health check | `GET /health` | `200 {"status":"ok","version":"<sha>"}` (vérifie MySQL) ; la version doit être égale à la révision de l'image | code ≠ 200 ou version différente |
| 6 | Smoke tests (`scripts/smoke-test.sh`) | URL de base | code HTTP et durée par page | une page ne renvoie pas le code attendu |
| 7 | Trace de la version | — | `deployment-record.json` (image, digest, révision, date, auteur, lien du run) | — |

Pages vérifiées par les smoke tests : `/health` (code + corps), `/`, `/connexion`, `/inscription`, `/inscription/etape/1`
(200), `/reservation/mes-reservations` (302 vers la connexion : contrôle d'accès actif), une URL inexistante (404).

Trace de la version déployée : résumé du job (onglet **Summary** du run) et artefact `deploy-test-<run_id>`
(`deployment-record.json`, `smoke-tests.txt`, `health.json`, `containers.txt`, `app.log`, `database.log`).

## 3 bis. Version stable et rollback automatique

| Situation | Action du workflow *Deploy test* |
|---|---|
| Déploiement et vérifications **réussis** (hors pull request) | l'image déployée est promue : tag GHCR **`stable`** |
| Une vérification **échoue** (conteneur, migrations, `/health`, smoke tests) | **rollback automatique** : `scripts/rollback.sh` remet en service l'image `stable` et la vérifie ; le job reste **en échec** (la nouvelle version n'est pas livrable) mais le service est rétabli |
| Aucun tag `stable` (tout premier déploiement) | rollback impossible, signalé par une erreur explicite |

Le tag `stable` désigne toujours la **dernière version déployée et vérifiée** ; `latest` désigne la dernière version
**construite**. Les deux peuvent différer : c'est précisément ce qui rend le rollback sûr.

Démonstration : *Actions* → *Deploy test* → **Run workflow** → cocher **simuler_echec** → *Run*. Le workflow déploie
la version, met en service une version défectueuse (`scripts/simulate-incident.sh`), les smoke tests échouent,
puis le rollback automatique rétablit la version `stable` (visible dans le **Summary**).
En ligne de commande : `gh workflow run deploy-test.yml -f simuler_echec=true`.

## 4. Accès et procédure

- **En CI** : GitHub → **Actions** → *Deploy test* → dernier run → **Summary** (version, digest, smoke tests) et artefact.
- **Déployer une version précise** : *Actions* → *Deploy test* → **Run workflow** → saisir le SHA → *Run*.
  En ligne de commande : `gh workflow run deploy-test.yml -f version=<SHA>`.
- **En local** (Docker Desktop, PowerShell), pour reproduire un déploiement depuis GHCR :

```powershell
gh auth token | docker login ghcr.io -u <utilisateur> --password-stdin
$env:DOCKER_IMAGE = "ghcr.io/mlajili38-la/wassalni-devops"
$env:APP_SECRET = "<valeur locale>"; $env:MYSQL_PASSWORD = "<valeur locale>"; $env:MYSQL_ROOT_PASSWORD = "<valeur locale>"
bash scripts/deploy-test.sh <SHA ou latest>
```

## 5. Preuves

`docs/preuves/lot6-deploiement.txt` : exécution locale et exécution GitHub Actions (liens des runs, sorties des vérifications).
