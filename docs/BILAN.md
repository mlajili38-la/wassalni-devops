# Bilan du projet

## 1. Ce qui est réalisé (vérifié par une exécution réelle)

| Lot | Réalisé | Preuve |
|---|---|---|
| 1. Git et collaboration | dépôt structuré, GitHub Flow (`main` + branches courtes), Conventional Commits, une branche et une PR par sujet, `CONTRIBUTING.md`, modèle de PR, suppression automatique des branches fusionnées ; **25 PR fusionnées** (20 de l'auteur, 5 Dependabot) | historique Git, onglet *Pull requests* |
| 2. CI et build | pipeline as code déclenché sur push et PR vers `main`, jobs dépendants (`quality` ∥ `security` → `container`), cache Composer et Trivy, actions GitHub sur Node 24, image versionnée par SHA | onglet *Actions*, `docs/PIPELINE.md` |
| 3. Qualité et tests | PHP-CS-Fixer, PHPStan niveau 5 (baseline réduite de 58 à **37**), **59 tests** dont 36 fonctionnels avec utilisateur connecté et 14 d'intégration sur une base MySQL `wassalni_test`, couverture **34,43 %** (seuil bloquant 30 %), rapports archivés | `docs/QUALITE-TESTS.md`, `docs/preuves/lot3-quality-gates.txt` |
| 4. Conteneurisation | `Dockerfile` **multi-étapes** (`base` / `build` / `runtime`, Composer et `unzip` absents de l'image publiée), `compose.yaml` (app + MySQL, réseau, volume, healthchecks), migrations au démarrage, image publiée dans GHCR | `docs/preuves/lot4-docker-mysql.txt`, packages GHCR |
| 5. Sécurité | Gitleaks, Trivy (dépendances + image), `composer audit`, Dependabot ; 60 → 0 vulnérabilités Composer ; image 6 → 0 HIGH/CRITICAL corrigeables ; **SEC-01 corrigé** (secret retiré, clé régénérée, mot de passe Gmail révoqué) ; matrice des secrets | `docs/SECURITE.md`, `docs/preuves/lot5-securite.txt` |
| 6. Déploiement | déploiement automatique de l'image GHCR après chaque CI verte sur `main`, déclenchement manuel ; vérification des conteneurs, des migrations, de `/health` (qui annonce la **version en service**, comparée à la révision de l'image) et des smoke tests ; promotion de la version vérifiée en tag **`stable`** | `docs/DEPLOIEMENT.md`, `docs/preuves/lot6-deploiement.txt` |
| 7. Supervision et rollback | logs collectés et documentés, sondes, **tableau de bord** (disponibilité, 5xx, temps de réponse, MTTD, MTTR), incident contrôlé, diagnostic par les logs, rollback manuel **et automatique** vers `stable` (dernier exercice : MTTD 4 s, MTTR 24 s) | `docs/SUPERVISION.md`, `docs/preuves/lot7-*` |

## 2. Défauts détectés par la chaîne

| # | Défaut | Détecté par | Correction |
|---|---|---|---|
| 1 | Pipeline et Docker sur PostgreSQL alors que l'application et les migrations sont MySQL | revue de cohérence | PR #2 : harmonisation MySQL 8.0 |
| 2 | Migrations impossibles sur une base vide (clés étrangères en double, erreur 1826) | test sur MySQL vide | PR #2 |
| 3-4 | Imports manquants (`RegistrationType`, `User`) → erreurs 500 | PHPStan | PR #4 |
| 5 | `Assert\Length(message:)` → erreur 500 sur `/inscription` | test d'intégration HTTP | PR #4 |
| 6 | `Assert\Range(message:)` → erreur 500 à l'ajout d'un véhicule | PHPStan + test de validation | PR #4 |
| 7 | Mapping Doctrine `Trajet.vehicule` sans `inversedBy` | CI (`doctrine:schema:validate`) | PR #4 |
| 8 | Secret de développement versionné (SEC-01) | Gitleaks | branche `fix/sec-01-app-secret` : valeur retirée de `.env.dev`, clé régénérée dans `.env.dev.local`, ancienne valeur considérée comme compromise |
| 9 | 4 CVE CRITICAL (Apache) + 2 HIGH (zlib) dans l'image | Trivy | PR #5 : durcissement du Dockerfile |
| 10 | 9 montées de version majeure proposées par Dependabot, dont 5 cassent la CI | CI | non fusionnées ; PR #17 : majeures ignorées |
| 11 | Tag `latest` publié dans le désordre (`latest` → `c311d47`, plus ancien que `30ba83a`) quand deux CI de `main` tournent en parallèle | lecture des preuves de l'exercice d'incident | PR #18 : publication sérialisée par branche |
| 12 | `UserRepository::findByRoles()` en échec (`JSON_CONTAINS` inconnue de DQL) | test d'intégration | réécrite avec une recherche dans le texte JSON, test ajouté |
| 13 | 21 erreurs de typage (utilisateur connecté non vérifié, collections non typées, dépendances inutiles) | PHPStan (baseline) | baseline réduite de 58 à 37 erreurs |
| 14 | Actions GitHub (`checkout`, `cache`, `upload-artifact`) sur Node 20, déprécié | avertissements de la CI | PR #27 : migration vers les versions Node 24 ; Dependabot accepte désormais les montées majeures des actions |
| 15 | Annotations `@var Collection<…>` placées après les attributs Doctrine, donc ignorées (baseline à 45 au lieu de 37) | contrôle du nombre d'entrées de la baseline | PR #32 : annotations déplacées avant les attributs |
| 16 | Opérateur `?->` superflu dans des tests fonctionnels | PHPStan dans la CI de la PR #30 | assertions `assertNotNull` explicites |
| 17 | Smoke tests dupliqués (boucle dans `ci.yml`, script dans le déploiement) : la CI vérifiait moins que le déploiement | revue du pipeline | PR #33 : `scripts/smoke-test.sh` réutilisé partout, version de `/health` vérifiée dès la CI |

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
| `git pull` refusé sur `main` | modifications locales non commitées (`.env.dev`, dossier de tests) | `git restore` du fichier déjà fusionné, `git stash` du travail en cours, puis branche dédiée |
| `git stash show stash@{0}` en erreur sous PowerShell | les accolades sont interprétées par PowerShell | écrire `'stash@{0}'` entre apostrophes |
| Versions différentes entre poste et CI (PHP 8.4 / PHPUnit 12.5.23 en local, PHP 8.3 / 12.5.45 en CI) | `vendor/` local non mis à jour après les PR Dependabot | `composer install` après chaque `git pull` ; la CI fait foi |
| Gain de taille du Dockerfile multi-étapes quasi nul (218 → 215 Mo compressés) | l'essentiel de l'image est la base `php:8.3-apache` ; Composer ne pèse que ≈ 3 Mo | gain présenté comme un gain de **sécurité** ; réduction de taille : image Alpine (amélioration) |

## 4. Limites et points non réalisés (signalés honnêtement)

- **Protection technique de `main` non activée** (dépôt privé, compte gratuit : l'API GitHub répond « Upgrade to GitHub Pro or make this repository public »).
  La règle « pas de push direct, PR + CI verte » a été **respectée en pratique** (toutes les fusions passent par une PR verte), mais elle n'est pas imposée par la plateforme.
- **Approbation manuelle** : remplacée par le déclenchement manuel `workflow_dispatch` (pas de relecteurs obligatoires sans Pro).
- **Revue de code par une autre personne non réalisée** (projet individuel) : compensée par une auto-revue argumentée (commentaires sur les lignes de la PR #29) et par les contrôles automatiques.
- **Environnement de test éphémère** (choix du projet) : pas d'URL permanente ; les preuves sont les runs, résumés et artefacts.
- **Couverture de tests : 34,43 % des lignes** (11,45 % avant les tests fonctionnels, 33,92 % juste après). Restent peu couverts : inscription en plusieurs étapes, tableaux de bord, avis, e-mails.
- **Baseline PHPStan de 37 erreurs** : uniquement des propriétés d'entités nullables face à des colonnes `NOT NULL`, gelées volontairement (voir `docs/QUALITE-TESTS.md` § 4).
- **Risque résiduel image** : 1 CRITICAL et 121 HIGH sans correctif Debian publié (règle `--ignore-unfixed`).
- **Rollback de la base** : le rollback change l'image mais pas le schéma ; les migrations ne vont que vers l'avant (aucune migration incompatible publiée à ce jour).
- **Supervision** limitée aux exercices et aux déploiements (environnement éphémère) : pas de surveillance continue ni d'alerte.
- `scripts/rollback.ps1` (Windows) n'a pas été rejoué ; la procédure démontrée est `scripts/rollback.sh`.

## 5. Indicateurs de suivi (au 22/11/2026)

Calculés par `scripts/indicateurs.py` à partir de l'historique GitHub (`gh run list`, `gh pr list`) ; détail : `docs/preuves/indicateurs-chaine.md`.

| Indicateur | Valeur |
|---|---|
| Taux de réussite des pipelines | CI : 84 % (64/76) ; **100 % sur `main` depuis la première CI verte (22/22)**. Les 12 échecs : 5 sur `main` pendant la mise en place (avant le 07/11), 5 sur des PR Dependabot de montée majeure (bloquées volontairement), 2 sur des branches corrigées avant fusion : **aucun défaut n'a atteint `main`** |
| Durée moyenne d'exécution | CI 2 min 53 s ; *Deploy test* 1 min 00 s ; *Incident drill* 1 min 21 s |
| Fréquence des intégrations et des déploiements | 25 PR fusionnées (20 de l'auteur, 5 Dependabot) sur 3 jours de travail, soit 8,3 par jour ; 17 déploiements en test (un par fusion sur `main`, plus les déploiements manuels) |
| Taux de couverture des tests | 11,45 % → 33,92 % (tests fonctionnels) → **34,43 %** des lignes au 22/11 (seuil bloquant 10 % → 30 %) |
| Défauts détectés avant le déploiement | 17 (tableau § 2), dont 16 avant tout déploiement |
| Vulnérabilités détectées et corrigées | Composer 60 → 0 ; image : 6 HIGH/CRITICAL corrigeables → 0 ; 1 secret versionné corrigé (SEC-01) |
| Taux d'échec des déploiements | 0 % (0/17 déploiements réels) ; l'échec volontaire de démonstration (`simuler_echec`) déclenche le rollback automatique |
| Délai de restauration | exercice d'incident : MTTD 4 à 6 s, MTTR 24 s, rollback 8 à 9 s |

## 6. Améliorations proposées

1. GitHub Pro (gratuit avec le GitHub Student Developer Pack) : protection technique de `main` et revue obligatoire.
2. Poursuivre la couverture (inscription, tableaux de bord, avis) et relever `COVERAGE_MIN` à chaque gain ; résorber la baseline PHPStan restante.
3. Environnement de test persistant gratuit (VM ou PaaS) avec URL, supervision continue (Uptime Kuma ou Prometheus/Grafana) et alertes.
4. Déploiement *blue/green* pour supprimer l'interruption pendant une mise en service ou un rollback.
5. Image plus légère (Alpine ou PHP-FPM séparé d'un serveur web) pour réduire la taille et les vulnérabilités résiduelles.
6. Signature des images (cosign) et SBOM publiés avec chaque version ; versions sémantiques (`v1.0.0`) en plus du SHA.
