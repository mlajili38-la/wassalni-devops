# Captures d'écran (preuves visuelles)

Captures du 22/11/2026. Règle : **aucun secret visible** (valeurs de `.env*`, mots de passe, jetons).

| # | Fichier | Contenu | Lot / étape de la démonstration |
|---|---|---|---|
| 1 | `01-pull-requests.png` | PR fusionnées, titres au format Conventional Commits | Lot 1 / étape 16 |
| 2 | `02-pr-auto-revue.png` | PR #29 : description et commentaires d'auto-revue sur les lignes | Lot 1 |
| 3 | `03-ci-graphe.png` | run CI #80 sur `main` : graphe `quality` ∥ `security` → `container`, artefacts | Lot 2 / étape 18 |
| 3a | `03a-ci-main-historique.png` | historique des CI sur `main` : toutes vertes | Lot 2 |
| 4 | `04-couverture.png` | étape *Coverage quality gate* : `34.43 %` (493/1432), seuil `30 %` | Lot 3 / étape 19 |
| 5 | `05-rapport-couverture.png` | rapport HTML de couverture (artefact `test-reports-<sha>`) | Lot 3 / étape 19 |
| 6 | `06-ghcr-tags.png` | package GHCR : la version `1fe7cdc…` porte `latest` et `stable` | Lot 4 / étape 20 |
| 7 | `07-securite-gitleaks.png` | Gitleaks : 57 commits analysés, `no leaks found` | Lot 5 / étape 22 |
| 7b | `07b-securite-trivy-image.png` | Trivy, règle bloquante sur l'image : `Total: 0 (HIGH: 0, CRITICAL: 0)` corrigeables | Lot 5 / étape 22 |
| 8 | `08-secrets-github.png` | noms des secrets `TEST_*` (valeurs masquées par GitHub) | Lot 5 |
| 9 | `09-smoke-tests-ci.png` | Summary CI : smoke tests de l'image, `/health` annonce le SHA construit | Lot 6 / étape 21 |
| 10 | `10-deploy-test.png` | Summary *Deploy test* : image, digest, révision, `health` avec la version | Lot 6 / étape 21 |
| 11 | `11-promotion-stable.png` | étape *Promote deployed version as stable* (rollback automatique ignoré car succès) | Lot 6 |
| 12 | `12-rollback-automatique.png` | *Deploy test* #34 (`simuler_echec`) : rollback automatique vers `stable` réussi, run en échec volontaire, annotations | Lot 7 / étape 23 |
| 12a | `12a-rollback-smoke-tests.png` | même run : déploiement et smoke tests OK, puis section rollback | Lot 7 |
| 13 | `13-tableau-de-bord.png` | tableau de bord de supervision (exercice de référence, `lot7-dashboard.html`) | Lot 7 / étape 23 |
| 14 | `14-fiche-incident.png` | Summary *Incident drill* : fiche d'incident (versions, détection, diagnostic) | Lot 7 / étape 23 |
| 14a | `14a-indicateurs-incident.png` | Summary *Incident drill* : indicateurs (disponibilité, 5xx, MTTD 4 s, MTTR 24 s) | Lot 7 |
| 15 | `15-docker-multi-etapes.png` | `composer : absent`, `unzip : absent` dans l'image publiée | Lot 4 |
