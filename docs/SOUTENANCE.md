# Plan de soutenance et scénario de démonstration

Durée indicative : 15 minutes de présentation et de démonstration, puis les questions.

## 1. Plan de la présentation (≈ 6 diapositives)

| # | Diapositive | Contenu | Support |
|---|---|---|---|
| 1 | Contexte | livraisons manuelles → erreurs, environnements différents, pas de retour arrière ; objectif : chaîne automatisée | énoncé |
| 2 | Application et architecture | WASSALNI, rôles, modèle de données, stack Docker (app + MySQL) | `docs/ARCHITECTURE.md` |
| 3 | La chaîne | schéma commit → CI → qualité ∥ sécurité → image → Trivy → GHCR → déploiement test → smoke/health → logs → rollback | `README.md`, `docs/PIPELINE.md` |
| 4 | Qualité et sécurité | règles bloquantes, 17 défauts détectés (16 avant tout déploiement), 59 tests, couverture 34,43 %, 60 → 0 vulnérabilités Composer, 6 → 0 HIGH/CRITICAL corrigeables, SEC-01 corrigé | `docs/BILAN.md` § 2, `docs/SECURITE.md` |
| 5 | Exploitation | déploiement automatique depuis GHCR, version tracée par `/health`, tag `stable`, rollback automatique, tableau de bord : MTTD 4 s, MTTR 24 s | `docs/SUPERVISION.md` § 6 |
| 6 | Bilan | indicateurs, limites (compte gratuit, couverture 34 %, environnement éphémère), améliorations | `docs/BILAN.md` |

## 2. Démonstration (étapes 16 à 24 de l'énoncé)

Préparation : Docker Desktop lancé, `gh` connecté, onglets ouverts sur le dépôt, *Actions* et *Packages*.

| Étape | Action | Ce qu'on montre |
|---|---|---|
| 16. Dépôt et branches | page du dépôt, `CONTRIBUTING.md`, liste des PR fusionnées | GitHub Flow, Conventional Commits, une PR par lot |
| 17. Petite modification | `git switch -c feat/demo` ; modifier un texte de `templates/home/` ; `git commit -m "feat(home): ..."` ; `git push` ; `gh pr create` | déclenchement par une PR |
| 18. Pipeline | onglet *Actions* : `quality` ∥ `security` → `container` | jobs, dépendances, logs |
| 19. Test et rapport | log *Run unit and integration tests* ; artefact `test-reports-<sha>` → `coverage-html/index.html`, `testdox.txt` | 59 tests dont 36 fonctionnels avec utilisateur connecté, base `wassalni_test`, couverture 34,43 % |
| 20. Image et registre | fusion de la PR → job `container` → *Packages* : tag = SHA du commit | image versionnée et publiée |
| 21. Déploiement en test | run *Deploy test* déclenché automatiquement → *Summary* | pull GHCR, conteneurs healthy, migrations, `/health` 200, smoke tests, version déployée |
| 22. Sécurité | log Gitleaks (`no leaks found`, alerte SEC-01 justifiée), Trivy (image : 0 HIGH/CRITICAL corrigeable) ; expliquer SEC-01 et le durcissement Apache/zlib | `docs/SECURITE.md` |
| 23. Incident et rollback | *Actions* → *Incident drill and rollback* → *Run workflow* ; pendant l'exécution, expliquer le scénario ; puis *Summary* : tableau de bord et fiche d'incident ; artefact : `dashboard.html`, `app-incident.log` (`TableNotFoundException`), `monitoring.csv`. Variante : *Deploy test* → *Run workflow* avec **simuler_echec** → rollback automatique vers `stable` | détection, diagnostic, rollback manuel et automatique, retour à la normale |
| 24. Choix et limites | `docs/BILAN.md` § 4 et 6 | honnêteté sur ce qui n'est pas réalisé |

Variante locale de l'étape 23 (sans attendre GitHub), dans Git Bash :

```bash
gh auth token | docker login ghcr.io -u <utilisateur> --password-stdin
export DOCKER_IMAGE=ghcr.io/mlajili38-la/wassalni-devops APP_PORT=8089 COMPOSE_PROJECT_NAME=wassalni-demo
export APP_SECRET=$(openssl rand -hex 16) MYSQL_PASSWORD=$(openssl rand -hex 16) MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16)
bash scripts/incident-drill.sh <SHA stable>
```

## 3. Questions probables et éléments de réponse

| Question | Réponse courte |
|---|---|
| Pourquoi un environnement éphémère ? | pas de serveur ni de coût, chaque déploiement part d'un état propre ; limite : pas d'URL permanente |
| Comment garantir qu'aucun secret n'est dans l'image ? | `.dockerignore` exclut `.env.*`, les secrets sont injectés au démarrage, image vérifiée (seul `.env` neutre), Gitleaks sur l'historique |
| Pourquoi ignorer des vulnérabilités (`--ignore-unfixed`) ? | aucun correctif Debian publié : non corrigeables aujourd'hui ; la CI échouera dès qu'un correctif existera ; elles restent visibles dans le rapport |
| Que se passe-t-il si une migration casse le rollback ? | les versions publiées n'ont pas de migration incompatible ; sinon : `doctrine:migrations:migrate <version>` avant de changer d'image |
| Pourquoi une baseline PHPStan ? | 58 erreurs à l'origine, réduites à 37 ; les restantes (entités nullables) demanderaient de modifier le code métier et les formulaires ; la baseline bloque toute nouvelle erreur |
| Pourquoi 34 % et pas plus ? | 11,45 % → 34,43 % grâce aux tests fonctionnels et au test de findByRoles ; inscription, tableaux de bord et avis restent à tester ; seuil cliquet à 30 % |
| Pourquoi un tag `stable` en plus de `latest` ? | `latest` = dernière version construite (peut être défectueuse) ; `stable` = dernière version déployée **et vérifiée** : c'est la seule cible sûre pour un rollback |
| Pourquoi le job reste rouge après un rollback automatique ? | la nouvelle version n'est pas livrable et l'équipe doit le voir ; le service, lui, est rétabli |
| Le Dockerfile multi-étapes a-t-il réduit l'image ? | très peu (218 → 215 Mo compressés) : le gain est la sécurité (Composer et `unzip` absents, code en lecture seule) |
