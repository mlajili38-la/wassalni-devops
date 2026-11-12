# WASSALNI — chaîne DevOps complète

Application web de **covoiturage** (Symfony 7.4, PHP 8.3, MySQL 8.0), utilisée comme support du mini-projet
« Conception et mise en œuvre d'une chaîne DevOps complète ». Le cœur du projet est l'industrialisation :
une modification du code déclenche une chaîne automatisée qui vérifie la qualité et la sécurité, construit une
image versionnée, la publie, la déploie en environnement de test, la vérifie et permet de revenir à une version stable.

```
Commit / PR ─► CI (qualité + tests ∥ sécurité) ─► Build image ─► Trivy ─► Déploiement éphémère + smoke tests ─► GHCR
                                                                                                                  │
                     Supervision / logs ◄─ Smoke tests + health check ◄─ Déploiement en test (image GHCR) ◄──────┘
                                    └─► Incident ─► Diagnostic ─► Rollback vers une image GHCR antérieure
```

## Documentation

| Sujet | Document |
|---|---|
| Application et architecture | [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) |
| Stratégie de branches, règles de contribution | [CONTRIBUTING.md](CONTRIBUTING.md) |
| Pipeline expliqué étape par étape (entrées, sorties, échecs) | [docs/PIPELINE.md](docs/PIPELINE.md) |
| Vue d'ensemble DevOps, démarrage local avec Docker | [docs/DEVOPS.md](docs/DEVOPS.md) |
| Qualité, tests, couverture | [docs/QUALITE-TESTS.md](docs/QUALITE-TESTS.md) |
| Sécurité, matrice des configurations et secrets | [docs/SECURITE.md](docs/SECURITE.md) |
| Déploiement en environnement de test | [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) |
| Supervision, logs, incident, rollback | [docs/SUPERVISION.md](docs/SUPERVISION.md) |
| Bilan, difficultés, améliorations, indicateurs | [docs/BILAN.md](docs/BILAN.md) |
| Fiche synthétique de remise | [docs/FICHE-REMISE.md](docs/FICHE-REMISE.md) |
| Plan de soutenance et scénario de démonstration | [docs/SOUTENANCE.md](docs/SOUTENANCE.md) |
| Preuves d'exécution | [docs/preuves/](docs/preuves/) |

## Démarrage rapide (Docker Desktop, PowerShell)

```powershell
Copy-Item .env.docker.example .env.docker      # puis remplacer les valeurs "replace-..."
docker compose --env-file .env.docker up --build -d --wait
Invoke-WebRequest http://localhost:8080/health  # 200 {"status":"ok"}
```

## Workflows GitHub Actions

| Workflow | Fichier | Déclencheurs |
|---|---|---|
| CI | `.github/workflows/ci.yml` | push sur `main`, PR vers `main` |
| Deploy test | `.github/workflows/deploy-test.yml` | après une CI verte sur `main`, manuel, PR (fichiers de déploiement) |
| Incident drill and rollback | `.github/workflows/incident-rollback.yml` | manuel, PR (scripts d'exploitation) |
| Dependabot | `.github/dependabot.yml` | hebdomadaire |
