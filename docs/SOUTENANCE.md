# Plan de soutenance et scénario de démonstration

Durée indicative : 15 minutes de présentation et de démonstration, puis les questions.

## 1. Plan de la présentation (≈ 6 diapositives)

| # | Diapositive | Contenu | Support |
|---|---|---|---|
| 1 | Contexte | livraisons manuelles → erreurs, environnements différents, pas de retour arrière ; objectif : chaîne automatisée | énoncé |
| 2 | Application et architecture | WASSALNI, rôles, modèle de données, stack Docker (app + MySQL) | `docs/ARCHITECTURE.md` |
| 3 | La chaîne | schéma commit → CI → qualité ∥ sécurité → image → Trivy → GHCR → déploiement test → smoke/health → logs → rollback | `README.md`, `docs/PIPELINE.md` |
| 4 | Qualité et sécurité | règles bloquantes, 10 défauts détectés avant déploiement, 60 → 0 vulnérabilités Composer, 6 → 0 HIGH/CRITICAL corrigeables dans l'image | `docs/BILAN.md` § 2, `docs/SECURITE.md` |
| 5 | Exploitation | déploiement automatique depuis GHCR, incident, diagnostic, rollback : MTTD 6 s, MTTR 24 s | `docs/SUPERVISION.md` § 6 |
| 6 | Bilan | indicateurs, limites (compte gratuit, couverture 11 %, environnement éphémère), améliorations | `docs/BILAN.md` |

## 2. Démonstration (étapes 16 à 24 de l'énoncé)

Préparation : Docker Desktop lancé, `gh` connecté, onglets ouverts sur le dépôt, *Actions* et *Packages*.

| Étape | Action | Ce qu'on montre |
|---|---|---|
| 16. Dépôt et branches | page du dépôt, `CONTRIBUTING.md`, liste des PR fusionnées | GitHub Flow, Conventional Commits, une PR par lot |
| 17. Petite modification | `git switch -c feat/demo` ; modifier un texte de `templates/home/` ; `git commit -m "feat(home): ..."` ; `git push` ; `gh pr create` | déclenchement par une PR |
| 18. Pipeline | onglet *Actions* : `quality` ∥ `security` → `container` | jobs, dépendances, logs |
| 19. Test et rapport | log *Run unit and integration tests* ; artefact `test-reports-<sha>` → `coverage-html/index.html`, `testdox.txt` | 21 tests, base `wassalni_test`, couverture |
| 20. Image et registre | fusion de la PR → job `container` → *Packages* : tag = SHA du commit | image versionnée et publiée |
| 21. Déploiement en test | run *Deploy test* déclenché automatiquement → *Summary* | pull GHCR, conteneurs healthy, migrations, `/health` 200, smoke tests, version déployée |
| 22. Sécurité | log Gitleaks (`no leaks found`, alerte SEC-01 justifiée), Trivy (image : 0 HIGH/CRITICAL corrigeable) ; expliquer SEC-01 et le durcissement Apache/zlib | `docs/SECURITE.md` |
| 23. Incident et rollback | *Actions* → *Incident drill and rollback* → *Run workflow* ; pendant l'exécution, expliquer le scénario ; puis *Summary* : fiche d'incident ; artefact : `app-incident.log` (`TableNotFoundException`), `monitoring.csv` | détection, diagnostic, rollback, retour à la normale |
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
| Pourquoi une baseline PHPStan ? | corriger 58 erreurs de typage aurait modifié le code métier ; la baseline bloque toute nouvelle erreur |
| Pourquoi la couverture est-elle si basse ? | contrôleurs non testés ; seuil anti-régression ; amélioration prévue |
