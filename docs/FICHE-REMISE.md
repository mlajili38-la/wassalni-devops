# Fiche synthétique de remise

| Information | Valeur |
|---|---|
| Nom(s) et prénom(s) | *à compléter* |
| Intitulé de l'application | **WASSALNI** — application web de covoiturage (conducteurs, passagers, administration) |
| Lien du dépôt Git | https://github.com/mlajili38-la/wassalni-devops (dépôt privé : accès à donner à l'enseignant) |
| Technologies de l'application | Symfony 7.4, PHP 8.3, Doctrine ORM et Migrations, MySQL 8.0, Twig, Apache 2.4 |
| Plateforme CI/CD utilisée | GitHub Actions : `ci.yml` (qualité, sécurité, image), `deploy-test.yml` (déploiement en test), `incident-rollback.yml` (incident et rollback) |
| Registre d'images / artefacts | GitHub Container Registry : `ghcr.io/mlajili38-la/wassalni-devops:<SHA du commit>` et `:latest` ; rapports en artefacts GitHub Actions |
| Environnement de test et procédure d'accès | stack Docker Compose (app + MySQL) créée dans GitHub Actions à partir de l'image GHCR, vérifiée puis détruite. Accès : *Actions* → *Deploy test* → résumé et artefact du run ; redéploiement manuel : *Run workflow* avec un SHA. Reproduction locale : `docs/DEPLOIEMENT.md` § 4 |
| Contrôle(s) de sécurité intégré(s) | Gitleaks (secrets, tout l'historique), Trivy (dépendances et image, règle HIGH/CRITICAL), `composer audit`, Dependabot (mises à jour + alertes) |
| Procédure de rollback | `scripts/rollback.sh <SHA stable>` : image GHCR antérieure, base conservée, vérification `/health` et smoke tests. Démontrée par le workflow *Incident drill and rollback* (MTTD 6 s, MTTR 24 s) ; voir `docs/SUPERVISION.md` § 5-6 |
| Date de remise | *à compléter* |
