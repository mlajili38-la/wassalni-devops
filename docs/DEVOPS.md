# Chaîne DevOps de WASSALNI

## Architecture et flux

WASSALNI est une application Symfony 7.4 / PHP 8.2+ avec PostgreSQL et PHPUnit. La branche de livraison est la branche par défaut du dépôt (`main` ou `master`) ; les changements se font dans des branches courtes et sont intégrés par pull request après revue.

Le workflow `.github/workflows/ci.yml` s'exécute sur les pushes et les pull requests ciblant `main`, `master` ou `develop` :

1. Valide les fichiers Composer et installe les dépendances depuis `composer.lock`.
2. Vérifie la syntaxe PHP, valide le conteneur Symfony, exécute `composer audit` et les tests PHPUnit. Le rapport JUnit est conservé comme artefact.
3. Construit l'image Docker identifiée par le SHA du commit, démarre l'application et PostgreSQL dans un environnement de test éphémère, puis vérifie `/health`.
4. Sur un push à la branche par défaut uniquement, publie l'image avec son SHA et le tag `latest` dans GitHub Container Registry (GHCR).

L'audit Composer sert de contrôle de sécurité des dépendances et fait échouer le pipeline si une vulnérabilité connue est détectée. Aucune valeur secrète de production n'est requise par le pipeline CI.

## Démarrage local avec Docker Compose

Prérequis : Docker Desktop avec le moteur Linux et Docker Compose v2.

Dans PowerShell, créer le fichier local d'environnement (ignoré par Git), remplacer les deux valeurs d'exemple par des valeurs locales aléatoires, puis démarrer les services :

```powershell
Copy-Item .env.docker.example .env.docker
docker compose --env-file .env.docker up --build -d --wait
Invoke-WebRequest http://localhost:8080/health
```

Le point de contrôle répond `200 {"status":"ok"}` lorsque l'application et PostgreSQL sont disponibles, et `503` si la base ne répond pas. Les mots de passe employés dans une URL PostgreSQL doivent être encodés en URL ; utiliser de préférence un mot de passe composé de caractères URL sûrs dans cet environnement local.

Arrêter l'environnement sans supprimer les données :

```powershell
docker compose --env-file .env.docker down
```

Consulter les journaux :

```powershell
docker compose --env-file .env.docker logs --follow app database
```

Apache écrit ses journaux d'accès/erreur sur la sortie du conteneur. Les erreurs applicatives Symfony/Monolog en production sont au format JSON sur `stderr`. La disponibilité est observable via le statut de santé du conteneur (`docker compose ps`) et le endpoint `/health`.

## Déploiement test et rollback

Le job CI exécute le déploiement dans un Compose éphémère et lance un smoke test HTTP après que le health check du conteneur est vert. Cette preuve ne constitue pas un déploiement persistant sur un serveur externe.

Sur un hôte de test disposant de Docker Compose, configurer `.env.docker` sans le committer et préciser l'image GHCR ainsi que sa version :

```powershell
$env:DOCKER_IMAGE = "ghcr.io/organisation/wassalni"
$env:APP_VERSION = "SHA_DU_COMMIT"
docker compose --env-file .env.docker pull app
docker compose --env-file .env.docker up -d --no-build --wait app
Invoke-WebRequest http://localhost:8080/health
```

Pour revenir à une version précédemment publiée, fournir son SHA (tag d'image immuable) :

```powershell
.\scripts\rollback.ps1 -Version SHA_STABLE
```

Le script récupère cette version de l'image, la redémarre, puis attend son health check. Vérifier ensuite `/health` et les journaux du service.

## Configuration et secrets

| Paramètre | Local / test | CI |
|---|---|---|
| `APP_ENV` | `prod` dans le conteneur Compose | `test` pour les contrôles PHP |
| `DATABASE_URL` | PostgreSQL du réseau Compose | URL de test fournie par le workflow |
| `APP_SECRET` | Valeur locale dans `.env.docker` | Valeur jetable propre au pipeline |
| Mot de passe DB | `.env.docker` (ignoré par Git) | Valeur jetable propre au pipeline |
| Version image | `APP_VERSION` / SHA de commit | SHA de `GITHUB_SHA` |

Ne pas placer de secret réel dans le dépôt, les journaux, les rapports ou les captures. Pour un serveur, stocker ces valeurs dans le gestionnaire de secrets de la plateforme et rendre le package GHCR accessible à cet hôte.

## Limites et amélioration

La publication GHCR requiert que le dépôt GitHub autorise `GITHUB_TOKEN` à publier des packages. Le déploiement CI est volontairement éphémère ; aucun hôte SSH ni secret d'infrastructure n'est présumé. L'image contient PHP 8.3, tandis que le projet accepte PHP 8.2 ou supérieur. La couverture, les tests d'intégration avec une base dédiée, la protection effective de `main` et le déploiement persistant sont à configurer/démontrer selon la plateforme et l'infrastructure disponibles.
