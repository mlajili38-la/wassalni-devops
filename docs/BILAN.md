# Bilan du projet

## 1. Ce qui est réalisé (vérifié par une exécution réelle)

| Lot | Réalisé | Preuve |
|---|---|---|
| 1. Git et collaboration | dépôt structuré, GitHub Flow, Conventional Commits, une branche et une PR par sujet, `CONTRIBUTING.md`, modèle de PR | historique Git, PR #1 à #5 et #15 à #19 (les #6 à #14 sont des PR Dependabot) |
| 2. CI et build | pipeline as code, déclenché sur push et PR, jobs dépendants, cache Composer et Trivy, image versionnée par SHA | onglet Actions |
| 3. Qualité et tests | PHP-CS-Fixer, PHPStan niveau 5 (baseline), 21 tests dont 13 d'intégration sur une base MySQL `wassalni_test`, couverture PCOV, règles bloquantes, rapports archivés | `docs/QUALITE-TESTS.md`, `docs/preuves/lot3-quality-gates.txt` |
| 4. Conteneurisation | `Dockerfile` durci, `compose.yaml` (app + MySQL, réseau, volume, healthchecks), migrations au démarrage, image publiée dans GHCR | `docs/preuves/lot4-docker-mysql.txt`, packages GHCR |
| 5. Sécurité | Gitleaks, Trivy (dépendances + image), `composer audit`, Dependabot + alertes ; 60 → 0 vulnérabilités Composer ; image 1524 → 454 vulnérabilités, 6 → 0 HIGH/CRITICAL corrigeables ; matrice des secrets | `docs/SECURITE.md`, `docs/preuves/lot5-securite.txt` |
| 6. Déploiement | déploiement automatique de l'image **GHCR** après chaque CI verte sur `main`, déclenchement manuel, vérification des conteneurs, des migrations, de `/health` et des smoke tests, trace de la version | `docs/DEPLOIEMENT.md`, `docs/preuves/lot6-deploiement.txt` |
| 7. Supervision et rollback | logs collectés et documentés, sondes, indicateurs, incident contrôlé, diagnostic par les logs, rollback GHCR (MTTD 6 s, MTTR 24 s) | `docs/SUPERVISION.md`, `docs/preuves/lot7-*` |

## 2. Défauts détectés par la chaîne avant tout déploiement

| # | Défaut | Détecté par | Correction |
|---|---|---|---|
| 1 | Pipeline et Docker sur PostgreSQL alors que l'application et les migrations sont MySQL | revue de cohérence | PR #2 : harmonisation MySQL 8.0 |
| 2 | Migrations impossibles sur une base vide (clés étrangères en double, erreur 1826) | test sur MySQL vide | PR #2 |
| 3-4 | Imports manquants (`RegistrationType`, `User`) → erreurs 500 | PHPStan | PR #4 |
| 5 | `Assert\Length(message:)` → erreur 500 sur `/inscription` | test d'intégration HTTP | PR #4 |
| 6 | `Assert\Range(message:)` → erreur 500 à l'ajout d'un véhicule | PHPStan + test de validation | PR #4 |
| 7 | Mapping Doctrine `Trajet.vehicule` sans `inversedBy` | CI (`doctrine:schema:validate`) | PR #4 |
| 8 | Secret de développement versionné (SEC-01) | Gitleaks | justifié, remédiation en cours |
| 9 | 4 CVE CRITICAL (Apache) + 2 HIGH (zlib) dans l'image | Trivy | PR #5 : durcissement du Dockerfile |
| 10 | 9 montées de version majeure proposées par Dependabot, dont 5 cassent la CI | CI | non fusionnées ; PR #17 : majeures ignorées |

## 3. Difficultés rencontrées

| Difficulté | Cause | Solution |
|---|---|---|
| Incohérence PostgreSQL / MySQL | configuration DevOps générée sans partir du schéma réel | partir des migrations existantes, tester sur une base vide |
| `schema:validate` filtré à tort en local | sortie mal lue (filtrage sur `[OK]`) | lire les sorties complètes ; la CI a révélé l'erreur de mapping |
| Faux échecs PHP-CS-Fixer sous Windows | `core.autocrlf` (CRLF) contre norme LF | `.gitattributes` (LF pour le code et les fichiers DevOps) |
| Build Docker : miroir Debian injoignable | coupure réseau passagère | diagnostic (HTTP/HTTPS depuis un conteneur), relance |
| Réduction de l'image : erreur `xargs` 123 | lignes `diversion by` de `dpkg-query` | filtrage de ces lignes |
| Messages Doctrine invisibles dans le script de déploiement | les commandes Doctrine écrivent sur **stderr** | `2>&1` + filtrage des dépréciations |
| 2 exercices d'incident annulés sur la PR #16 | groupe de concurrence partagé entre *Deploy test* et *Incident drill* : GitHub annule l'exécution en attente | PR #18 : un groupe par workflow et par version ; exercice relancé manuellement sur `main` (succès, MTTR 25 s) |
| Stack Compose « unhealthy » après redéploiement local | volume MySQL conservé avec d'anciens mots de passe | `down -v` avec les bonnes variables ; documenté comme cas d'incident |
| Limites du compte GitHub gratuit (dépôt privé) | protection de branche, règles et relecteurs d'environnement réservés à Pro | compensation documentée (ci-dessous) |

## 4. Limites et points non réalisés (signalés honnêtement)

- **Protection technique de `main` non activée** : l'API GitHub répond « Upgrade to GitHub Pro or make this repository public ».
  La règle « pas de push direct, PR + CI verte » a été **respectée en pratique** (toutes les fusions passent par une PR verte), mais elle n'est pas imposée par la plateforme.
- **Approbation manuelle** : remplacée par le déclenchement manuel `workflow_dispatch` (pas de relecteurs obligatoires sans Pro).
- **Environnement de test éphémère** (choix du projet) : pas d'URL permanente ; les preuves sont les runs, résumés et artefacts.
- **Couverture de tests faible : 11,45 % des lignes.** Les contrôleurs volumineux ne sont pas testés ; le seuil (10 %) empêche seulement la régression.
- **Baseline PHPStan de 58 erreurs** (typage des entités) non résorbée.
- **SEC-01** : remédiation en cours (la valeur reste dans l'historique ; le `.env.dev` vidé reste à commiter par le propriétaire).
- **Risque résiduel image** : 1 CRITICAL et 121 HIGH sans correctif Debian publié.
- **Revue de code** : projet individuel, les PR sont auto-relues (description, checklist, CI), sans second relecteur.
- `scripts/rollback.ps1` (Windows) n'a pas été rejoué ; la procédure démontrée est `scripts/rollback.sh`.
- `UserRepository::findByRoles()` est cassée (fonction `JSON_CONTAINS` inconnue de Doctrine) ; non utilisée, documentée.

## 5. Indicateurs de suivi (au 12/11/2026)

| Indicateur | Valeur |
|---|---|
| Taux de réussite CI | 61 % sur 31 exécutions. Les échecs sont : 5 sur `main` avant la PR #1 (mise en place), 5 sur des PR Dependabot majeures (bloquées volontairement), 2 sur des branches corrigées avant fusion. **100 % sur `main` depuis la PR #1** |
| Durée moyenne d'exécution | CI ≈ 2 min 15 (moyenne), Deploy test ≈ 1 min, Incident drill ≈ 2 min 30 |
| Fréquence | 19 PR (dont 9 Dependabot) ; un déploiement en test automatique par fusion sur `main` |
| Couverture | 11,45 % des lignes, 25,8 % des méthodes |
| Défauts détectés avant déploiement | 10 (tableau § 2) |
| Vulnérabilités | Composer 60 → 0 ; image : 6 HIGH/CRITICAL corrigeables → 0 |
| Échec des déploiements en test | 0 sur 9 exécutions de *Deploy test* (automatiques, manuelles et sur PR) |
| Délai de restauration (exercice) | MTTD 6 s, MTTR 24 s |

## 6. Améliorations proposées

1. Passer le dépôt en public ou utiliser GitHub Pro (Student Pack) : protection de `main`, relecteurs obligatoires, environnement `test` avec approbation.
2. Augmenter la couverture : tests HTTP authentifiés (réservation, administration) puis relever `COVERAGE_MIN` ; résorber la baseline PHPStan.
3. Environnement de test persistant gratuit (VM ou PaaS) avec URL, et déploiement *blue/green* pour éviter l'interruption pendant une mise en service.
4. Image plus légère (Alpine ou distroless + PHP-FPM) pour réduire les vulnérabilités résiduelles.
5. Supervision continue : Prometheus/Grafana ou Uptime Kuma, alertes, et ajout de la version dans la réponse de `/health`.
6. Signature des images (cosign) et SBOM publiés avec chaque version.
