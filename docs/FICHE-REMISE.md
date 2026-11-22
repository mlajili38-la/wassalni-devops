# Fiche synthétique de remise

| Information | Valeur |
|---|---|
| Nom(s) et prénom(s) | Mohamed lajili  |
| Intitulé de l'application | **WASSALNI** — application web de covoiturage (conducteurs, passagers, administration) |
| Lien du dépôt Git | https://github.com/mlajili38-la/wassalni-devops — dépôt **privé** : l'enseignant est ajouté comme collaborateur (*Settings → Collaborators*) dès qu'il communique son compte GitHub ; l'accès au package GHCR suit le dépôt |
| Technologies de l'application | Symfony 7.4, PHP 8.3, Doctrine ORM et Migrations, MySQL 8.0, Twig, Apache 2.4 |
| Plateforme CI/CD utilisée | GitHub Actions : `ci.yml` (qualité, tests, sécurité, image, smoke tests), `deploy-test.yml` (déploiement en test, promotion `stable`, rollback automatique), `incident-rollback.yml` (incident contrôlé, tableau de bord, rollback) |
| Registre d'images / artefacts | GitHub Container Registry : `ghcr.io/mlajili38-la/wassalni-devops` avec les tags `<SHA du commit>`, `latest` (dernière version construite) et `stable` (dernière version déployée et vérifiée) ; rapports de tests, de sécurité, de déploiement et tableau de bord en artefacts GitHub Actions |
| Environnement de test et procédure d'accès | stack Docker Compose (app + MySQL) créée dans GitHub Actions à partir de l'image GHCR, vérifiée puis détruite. Accès : *Actions* → *Deploy test* → *Summary* et artefact du run ; redéploiement manuel : *Run workflow* (version au choix). Reproduction locale : `docs/DEPLOIEMENT.md` § 4 |
| Contrôle(s) de sécurité intégré(s) | Gitleaks (secrets, tout l'historique), Trivy (dépendances et image, règle HIGH/CRITICAL corrigeables), `composer audit`, Dependabot (mises à jour et alertes) |
| Procédure de rollback | **automatique** : si une vérification échoue après un déploiement, *Deploy test* remet en service l'image `stable` (`scripts/rollback.sh`) ; **manuelle** : `scripts/rollback.sh <SHA stable>`. Base conservée, vérification `/health` et smoke tests. Démontrée par *Incident drill and rollback* (MTTD 4 s, MTTR 24 s) et par *Deploy test* avec l'option `simuler_echec` ; voir `docs/DEPLOIEMENT.md` § 3 bis et `docs/SUPERVISION.md` |
| Date de remise | 27/11/2026 |
