# Le pipeline expliqué étape par étape

Trois workflows GitHub Actions, définis comme du code dans `.github/workflows/`. Chaque étape est **bloquante** :
au premier échec, le job s'arrête en rouge et les jobs qui en dépendent ne s'exécutent pas. Aucune erreur n'est masquée.

```
                    ┌──────────── CI (ci.yml) : push sur main, PR vers main ────────────┐
 push / PR ──►      │  quality ─────────┐                                                │
                    │                   ├──► container : build ► Trivy ► compose ► smoke ► publication GHCR (main) │
                    │  security ────────┘                                                │
                    └────────────────────────────────────────────────────────────────────┘
                                         │ CI verte sur main (workflow_run)
                                         ▼
                    Deploy test (deploy-test.yml) : pull GHCR ► compose ► conteneurs ► migrations ► /health ► smoke ► trace
                    Incident drill and rollback (incident-rollback.yml, manuel) : stable ► défaut ► détection ► logs ► rollback
```

## 1. Workflow CI — job `quality` (« Quality, tests and dependency audit »)

| Étape | Entrée | Sortie | Échoue si… |
|---|---|---|---|
| Checkout, PHP 8.3 (+ `intl`, `pdo_mysql`, PCOV) | commit | environnement PHP | — |
| Cache Composer | `composer.lock` | cache restauré | — |
| Validate Composer files | `composer.json`, `composer.lock` | — | fichiers invalides ou désynchronisés |
| Install dependencies | `composer.lock` | `vendor/` (versions exactes) | dépendance introuvable |
| Check PHP syntax | `src/`, `tests/` | — | erreur de syntaxe |
| Code style (PHP-CS-Fixer) | `src/`, `tests/` | diff des écarts | un fichier n'est pas au format `@Symfony` |
| Static analysis (PHPStan niv. 5) | code + baseline | annotations sur la PR | **nouvelle** erreur hors baseline |
| Validate Symfony container | configuration | — | service mal configuré |
| Audit dependencies | `composer.lock` | liste des avis | vulnérabilité connue |
| Prepare test database | service MySQL 8.0 jetable | base `wassalni_test` migrée | migration en échec, mapping ou schéma invalide |
| Unit + integration tests with coverage | tests, base de test | `junit.xml`, `testdox.txt`, `clover.xml`, `coverage-html/` | un test échoue |
| Coverage quality gate | `clover.xml` | taux de couverture | couverture < `COVERAGE_MIN` (30 %) |
| Archive reports | `build/reports/` | artefact `test-reports-<sha>` | — |

## 2. Workflow CI — job `security` (« Security scans (secrets and dependencies) »)

| Étape | Entrée | Sortie | Échoue si… |
|---|---|---|---|
| Checkout (historique complet) | dépôt | tous les commits | — |
| Gitleaks | historique Git, `.gitleaksignore` | `gitleaks.json` (masqué) | secret non justifié |
| Trivy (dépendances) | `composer.lock` | log | vulnérabilité HIGH/CRITICAL |
| Archive | `build/security/` | artefact `security-reports-<sha>` | — |

## 3. Workflow CI — job `container` (« Build, deploy-test and publish image »), après `quality` **et** `security`

| Étape | Entrée | Sortie | Échoue si… |
|---|---|---|---|
| Build versioned image (`--pull`) | `Dockerfile`, code | image `wassalni-ci:<sha>`, label `revision` | build en erreur |
| Trivy (rapport complet) | image | `trivy-image.json`, `trivy-image.txt` | — (rapport) |
| Trivy (règle) | image | log | HIGH/CRITICAL **avec correctif** |
| Deploy to ephemeral environment | image, `compose.yaml` | stack `app` + `database` healthy (healthchecks Docker) | service non healthy |
| Verify database migrations | conteneur `app` | `Up-to-date`, schéma synchronisé | migration manquante |
| Health check and version | `GET /health` | corps JSON | code ≠ 200 ou version ≠ SHA du commit construit |
| Smoke tests (`scripts/smoke-test.sh`) | URL locale | une ligne par page (code, durée) + résumé du job | une page ne renvoie pas le code attendu (200, 302 pour une page protégée, 404 pour une page inexistante) |
| Container logs on failure | — | logs `app`/`database` | (seulement en cas d'échec) |
| Publish to GHCR (**push sur `main` uniquement**) | image | `ghcr.io/<dépôt>:<sha>` et `:latest` | authentification ou push refusé |
| Stop environment | — | — | — |

## 4. Workflow Deploy test — voir [DEPLOIEMENT.md](DEPLOIEMENT.md) § 3

Entrée : tag GHCR (SHA, ou `latest`). Sorties : `deployment-record.json`, smoke tests, logs (artefact `deploy-test-<run_id>`) et
résumé du job. Échoue si l'image est introuvable, si un conteneur n'est pas `healthy`, si les migrations ne sont pas à jour, si `/health` ≠ 200 ou si un smoke test échoue.

## 5. Workflow Incident drill and rollback — voir [SUPERVISION.md](SUPERVISION.md) § 5-6

Entrée : version stable (SHA). Sorties : fiche d'incident (résumé), `monitoring.csv`, logs, `rollback-record.json`
(artefact `incident-drill-<run_id>`). Échoue si l'incident n'est pas détecté ou si le rollback ne rétablit pas `/health` et les smoke tests.

## 6. Durées observées (runners GitHub)

`security` ≈ 30-40 s, `quality` ≈ 1 min 15, `container` ≈ 2-2 min 40, Deploy test ≈ 1 min, Incident drill ≈ 1 min 30.
CI complète ≈ 3 à 4 minutes (les jobs `quality` et `security` s'exécutent en parallèle).
