# Sécurité, configurations et secrets (Lot 5)

## 1. Contrôles de sécurité intégrés au pipeline

| Contrôle | Outil (version figée) | Job / étape CI | Entrée | Sortie | Échoue si… |
|---|---|---|---|---|---|
| Détection de secrets | Gitleaks `v8.24.3` | `security` → *Secret scan of the whole Git history* | Tout l'historique Git (`fetch-depth: 0`) | `gitleaks.json` (valeurs masquées par `--redact`) | un secret absent de `.gitleaksignore` est trouvé |
| Vulnérabilités des dépendances PHP | Trivy `0.58.1` (`fs`) + `composer audit` | `security` + `quality` | `composer.lock` | log du job | une vulnérabilité **HIGH/CRITICAL** est présente (Trivy) ou toute vulnérabilité connue (`composer audit`) |
| Vulnérabilités de l'image Docker | Trivy `0.58.1` (`image`) | `container` → *Image security gate* | image `wassalni-ci:<sha>` | `trivy-image.json` (toutes sévérités), `trivy-image.txt` (HIGH/CRITICAL) | une vulnérabilité **HIGH/CRITICAL avec correctif disponible** est présente |
| Mises à jour automatiques | Dependabot | `.github/dependabot.yml` | Composer, image Docker, GitHub Actions | PR hebdomadaires | rien en soi : chaque PR repasse tout le pipeline |

Ordre dans la chaîne : `quality` et `security` s'exécutent en parallèle ; `container` ne démarre que si **les deux** sont verts.
Dans `container`, la règle Trivy s'exécute **après le build et avant le déploiement de test et la publication GHCR** :
une image vulnérable n'est jamais déployée ni publiée.
Rapports archivés : artefacts `security-reports-<sha>` et `image-security-reports-<sha>`.

## 2. Résultats et actions correctives

### SEC-01 — Secret de développement dans l'historique Git (Gitleaks)

| | |
|---|---|
| Constat | 1 alerte `generic-api-key` : `APP_SECRET` dans `.env.dev`, ligne 3, commit initial `a70e004`. |
| Analyse | Clé utilisée **uniquement** quand `APP_ENV=dev` (poste du développeur). Elle n'est utilisée ni en test (`.env.test`), ni en CI (valeur jetable du workflow), ni dans le conteneur (`APP_SECRET` injecté au démarrage depuis `.env.docker`, non versionné). Impact : signature des jetons CSRF et des cookies « se souvenir de moi » d'une instance de développement locale. |
| Décision | **Alerte acceptée et justifiée**, enregistrée par son empreinte exacte dans `.gitleaksignore`. L'historique n'est pas réécrit (règle du projet : pas de force push). Le contrôle reste actif : toute nouvelle fuite, y compris une nouvelle valeur dans `.env.dev`, fait échouer la CI. |
| Remédiation | **Remédiation réalisée** (branche `fix/sec-01-app-secret`, fusionnée par PR) : 1) la valeur est retirée de `.env.dev` (`APP_SECRET=` vide dans la version versionnée) ; 2) une nouvelle clé, régénérée, est placée dans `.env.dev.local` (non versionné, ignoré via `/.env.*.local`, vérifié par `git check-ignore -v .env.dev.local`) ; chaque développeur génère la sienne (`php -r "echo bin2hex(random_bytes(16));"`) ; 3) l'ancienne valeur reste dans l'historique (non réécrit) : elle est **considérée comme compromise** (le dépôt a été temporairement public) et n'est jamais réutilisée. L'entrée de `.gitleaksignore` ne couvre que le commit initial `a70e004` : une nouvelle valeur commitée ferait échouer la CI. |
| Vérification | `php bin/console debug:dotenv --env=dev` : `APP_SECRET` provient de `.env.dev.local`, colonne `.env.dev` vide ; `git show main:.env.dev` : `APP_SECRET=` ; CI verte, Gitleaks sans nouvelle fuite. |

Autre secret signalé par le propriétaire : un **mot de passe d'application Gmail** (`MAILER_DSN`, stocké uniquement dans
`.env.local`, non versionné). Gitleaks ne le détecte pas dans l'historique, mais la valeur a été exposée hors du dépôt :
il a été **révoqué le 22/11/2026** dans le compte Google (*Mots de passe des applications*) et remplacé par un nouveau,
placé uniquement dans `.env.local`. Règle retenue : masquer les valeurs sensibles avant de partager une sortie de
`debug:dotenv`, de variables d'environnement ou de logs.

### SEC-02 — Vulnérabilités des dépendances PHP (composer audit, Trivy)

| Avant | Après | Action |
|---|---|---|
| 60 vulnérabilités (`composer audit`) | **0** (`composer audit`) ; Trivy : **98 paquets analysés, 0 vulnérabilité** | Mise à jour des dépendances (réalisée avant le Lot 5) |

### SEC-03 — Vulnérabilités de l'image Docker (Trivy)

| Image | Total | CRITICAL | HIGH | MEDIUM | LOW | UNKNOWN | HIGH/CRITICAL **avec correctif** |
|---|---|---|---|---|---|---|---|
| Avant (`php:8.3-apache` + outils de compilation) | 1524 | 5 | 147 | 464 | 880 | 28 | **6** (Apache : 4 CRITICAL, zlib : 2 HIGH) |
| Après durcissement du `Dockerfile` | **454** | 1 | 121 | 126 | 186 | 20 | **0** |

Actions correctives (`Dockerfile`) :
1. `apt-get upgrade` au build : Apache `2.4.69` → `2.4.71` (CVE CRITICAL corrigée) et zlib `deb13u1` (2 HIGH corrigées) ;
2. suppression des outils de compilation (`gcc`, `g++`, `make`, `autoconf`, en-têtes `-dev`, `linux-libc-dev`) après la compilation des extensions PHP : **190 → 132 paquets** dans l'image, surface d'attaque réduite ;
3. `docker build --pull` en CI : l'image de base est toujours la plus récente.
4. **construction en plusieurs étapes** (*multi-stage build*, étapes `base`, `build`, `runtime`) : Composer, `unzip`
   et les fichiers de construction restent dans l'étape `build` ; l'image publiée ne contient que PHP, Apache,
   le code, `vendor/` (sans dépendances de développement) et les assets compilés. Le code appartient à `root`
   (lecture seule pour Apache), seul `var/` est modifiable par `www-data`.

Vérification fonctionnelle après durcissement : extensions `intl` et `pdo_mysql` présentes, stack Compose `healthy`,
migrations appliquées, `/health`, `/`, `/connexion`, `/inscription`, `/inscription/etape/1` → HTTP 200.

**Risque résiduel accepté** : 1 CRITICAL (`libxml2`, statut Debian *affected*) et 121 HIGH (principalement `krb5`, `libxml2`,
`util-linux`, `expat`) **sans correctif publié par Debian** : aucune mise à jour ne peut les corriger aujourd'hui.
La règle `--ignore-unfixed` les laisse passer, mais **dès qu'un correctif est publié, le pipeline échoue** jusqu'à la reconstruction de l'image.
Elles restent visibles dans le rapport complet `trivy-image.json` de chaque exécution.
Piste d'amélioration : image de base plus légère (par exemple `php:8.3-fpm-alpine` + serveur web séparé), à évaluer car cela change l'architecture.

## 3. Matrice des environnements, configurations et secrets

Aucune valeur secrète n'est écrite dans ce document.

| Paramètre | Sensible | Développement (poste local, WAMP) | Test automatisé (CI `quality`) | Test conteneurisé (Compose local / CI `container`) | Où est stockée la valeur |
|---|---|---|---|---|---|
| `APP_ENV` / `APP_DEBUG` | non | `dev` / `1` | `test` / `1` | `prod` / `0` | `.env`, workflow, `compose.yaml` |
| `APP_SECRET` | **oui** | `.env.dev.local` (non versionné ; `.env.dev` ne contient qu'une valeur vide) | valeur jetable du workflow | `.env.docker` (local) / valeur jetable (CI) | fichiers ignorés par Git ; jamais dans l'image |
| `DATABASE_URL` | **oui** (mot de passe) | `.env` : MySQL WAMP local | service MySQL jetable du job, base `wassalni_test` | construite par `compose.yaml` à partir des variables `MYSQL_*` | runtime uniquement |
| `MYSQL_PASSWORD` / `MYSQL_ROOT_PASSWORD` | **oui** | — | valeur jetable du workflow | `.env.docker` (local) / valeur jetable (CI) | `.env.docker` ignoré par Git |
| `MAILER_DSN` | **oui** si SMTP réel | `.env.local` (non versionné) | `null://null` (`.env`) | `null://null` (`.env`) | `.env.local` ignoré par Git |
| `APP_VERSION` | non | — | — | SHA du commit (`GITHUB_SHA`) | workflow, label de l'image |
| `GITHUB_TOKEN` | **oui** | — | — | publication GHCR | secret automatique de GitHub Actions |

Règles appliquées :
- `.gitignore` exclut `.env.local`, `.env.*.local` et `.env.docker` ; `.dockerignore` exclut `.env.*` : **aucun secret dans l'image** (vérifié : seul le `.env` neutre y est présent).
- Les valeurs `ci-only-…` du workflow sont **jetables** : elles protègent des services éphémères détruits à la fin du job et ne donnent accès à rien d'autre.
- Les secrets d'un environnement persistant (Lot 6) sont stockés dans **GitHub → Settings → Environments → `test` → Secrets**, jamais dans le dépôt.

## 4. Reproduire les contrôles en local (PowerShell + Docker)

```powershell
# Secrets : historique Git uniquement (ne lit pas .env.local/.env.docker, non suivis), valeurs masquées
docker run --rm -v "${PWD}:/repo:ro" ghcr.io/gitleaks/gitleaks:v8.24.3 git /repo --redact --gitleaks-ignore-path /repo/.gitleaksignore

# Dépendances : ne monter QUE le fichier Composer
docker run --rm -v "${PWD}\composer.lock:/src/composer.lock:ro" aquasec/trivy:0.58.1 fs --scanners vuln --severity HIGH,CRITICAL /src

# Image
docker build --pull -t wassalni:scan .
docker run --rm -v //var/run/docker.sock:/var/run/docker.sock aquasec/trivy:0.58.1 image --scanners vuln --severity HIGH,CRITICAL --ignore-unfixed wassalni:scan
```

Preuves détaillées : `docs/preuves/lot5-securite.txt`.
