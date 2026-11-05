# 2. Stratégie, calendrier et critères de campagne

## Contrôle du document

| Champ | Valeur |
|---|---|
| Document | Plan de test WASSALNI |
| Version | 1.1 |
| Date | 5 novembre 2026 |
| Statut | Version préparée pour revue; critères et périmètre API à faire approuver |
| Propriétaire | Responsable de campagne / étudiant |
| Approbateur | Responsable produit ou enseignant, à renseigner |
| Historique | 1.0 — première version du dossier; 1.1 — détail des outils, du dépôt Git et des règles de campagne |

Le numéro de version du document permet d'identifier son contenu. Il ne remplace pas un commit Git : pour figer une version, ajouter les fichiers au dépôt, créer un commit et consigner son SHA dans le rapport d'exécution. Aucun commit n'est créé automatiquement dans le cadre de ce travail.

## 2.0 Objectifs et périmètre de la campagne

L'objectif est de fournir des preuves reproductibles sur les parcours à risque de WASSALNI : droits des utilisateurs, recherche/publication de trajets, réservation et restitution des places, et traitement des réclamations. La campagne vérifie les exigences de [la matrice de traçabilité](03-exigences-tracabilite.md) et exécute les cas de [la suite fonctionnelle](04-cas-de-test-donnees.md).

La campagne porte sur la révision identifiée par un SHA Git. Elle ne modifie pas les règles métier. Toute règle encore ambiguë est bloquée jusqu'à arbitrage. L'API est limitée à l'endpoint JSON observé; l'acceptation doit confirmer si ce périmètre suffit.

## 2.1 Niveaux et techniques

| Niveau | Objet | Technique / outil | Statut de l'outil dans le dépôt | Preuve attendue |
|---|---|---|---|---|
| Unitaire | Règles isolées de réservation/annulation et validations | PHPUnit; Arrange–Act–Assert; assertions; stub/mock selon l'interaction testée | PHPUnit est déclaré; 7 tests existent, exécution à confirmer | Sortie PHPUnit, tests isolés reproductibles |
| Intégration | Services/repositories, Doctrine et base de test | PHPUnit + kernel Symfony + base dédiée; fixtures déterministes | À mettre en place; aucune preuve d'intégration exécutée | Résultat CRUD, assertions en base et nettoyage |
| Système | Parcours HTTP et règles de rôles | Symfony WebTestCase/BrowserKit; client HTTP | BrowserKit est déclaré en dépendance; scénarios à réaliser | Requête, statut, contenu utile, utilisateur de test |
| API | Échange JSON actuellement identifié | Collection Bruno/Postman ou PHPUnit HTTP client; session authentifiée | Aucun outil de collection confirmé; choisir un format versionnable avant semaine 8 | Méthode, en-têtes, corps, statut et JSON |
| Acceptation | Scénarios métier de passager, conducteur et administrateur | Recette manuelle guidée par les cas du catalogue | À exécuter avec le responsable métier | Visa métier par scénario |
| Interface | Parcours critique du navigateur | Playwright (option proposée, à approuver et configurer) | Non confirmé dans les dépendances actuelles | Trace/capture anonymisée et résultat d'assertions |
| Non fonctionnel | Performance et accessibilité de base | Outil choisi selon le périmètre accepté; environnement contrôlé | À choisir; aucun résultat disponible | Configuration, mesures, seuils et limites |

Les outils optionnels ne doivent être ajoutés au projet qu'après approbation du périmètre et des dépendances nécessaires. Ne pas présenter un outil proposé comme déjà installé ou exécuté.

### Stratégie API

Le code examiné expose une route JSON de réservation AJAX, mais pas de surface REST complète identifiée. Tester cette route est pertinent pour la semaine 8; ne pas présenter cette couverture comme une campagne REST complète. Si le besoin impose une API REST, le responsable produit doit fournir le contrat, la version, les routes et les règles d'authentification avant d'élargir la matrice.

## 2.2 Environnements et données

- Environnement de test isolé, `APP_ENV=test`; aucune donnée réelle.
- Base PostgreSQL dédiée (Compose déclare PostgreSQL 16); ne jamais exécuter les scénarios destructifs sur la base de développement/production.
- L'URL `DATABASE_URL` doit pointer vers la base de test confirmée par l'équipe. Vérifier la connexion et les migrations avant la campagne; ne jamais supposer que le service Compose est démarré.
- Mailpit ou transport de mail factice si les flux d'inscription/notification sont inclus.
- Fixtures stables: utilisateur passager A et B, conducteur, administrateur, véhicule, trajets valide/à venir/complet et réservations aux différents statuts.
- Horloge, fuseau, montants et dates de départ documentés par exécution. Utiliser une date future suffisamment éloignée pour les tests qui dépendent du temps.
- Nettoyer les données créées après chaque test d'intégration; conserver uniquement les preuves anonymisées.

### Configuration minimale attendue

| Paramètre | Valeur / règle |
|---|---|
| Application | Symfony, `APP_ENV=test` |
| Runtime | PHP `>= 8.2`, conforme à `composer.json` |
| Dépendances | Installer depuis `composer.lock`; ne pas modifier le verrouillage pour une simple exécution |
| Base | PostgreSQL isolé, version correspondant au Compose (16 par défaut); schéma créé par migrations |
| Comptes | Fixtures fictives propres à l'environnement de test |
| Sorties | Résultats PHPUnit, cas manuels et captures anonymisées; ne pas versionner secrets, `.env.local`, données personnelles ou résultats volumineux |

## 2.3 Rôles et responsabilités

| Rôle | Responsabilité |
|---|---|
| Testeur/étudiant | Préparer les données, exécuter les cas, joindre les preuves et enregistrer les anomalies |
| Développeur | Corriger les défauts, identifier la révision corrigée et fournir les changements |
| Responsable produit/enseignant | Confirmer les ambiguïtés et les critères d'acceptation, arbitrer le risque résiduel, signer la recette |
| Responsable de campagne | Geler la version testée, suivre les résultats, décider de la recommandation de sortie |

Une même personne peut tenir plusieurs rôles dans un projet d'étude, mais la validation métier doit être explicitement consignée.

## 2.3.1 Organisation du dépôt Git

Le plan et les livrables sont regroupés sous `docs/dossier-tests/`. Les tests automatisés restent à côté du projet dans `tests/`, conformément au `phpunit.dist.xml`, qui découvre cette arborescence.

```text
tests/
  Service/              # tests unitaires des services métier
  Integration/          # à créer lors de la semaine 7 si nécessaire
  Functional/           # tests HTTP Symfony; réutiliser si cette suite existe
  e2e/                  # à créer uniquement si un outil navigateur est approuvé
docs/
  dossier-tests/
    README.md
    01-cadrage-risques.md
    02-strategie-calendrier.md
    03-exigences-tracabilite.md
    04-cas-de-test-donnees.md
    05-etats-exploration-anomalies.md
    06-automatisation-bilan.md
```

Les répertoires `Integration/` et `e2e/` sont des emplacements proposés, pas des suites déjà présentes. Ne pas créer de fichiers de test vides pour simuler un livrable.

### Flux Git proposé

1. Partir de la branche de référence validée par l'équipe; le dépôt a été observé sur `master`, mais la politique de branche doit être confirmée.
2. Créer une branche de travail courte, par exemple `test/semaine-02-plan` ou `test/semaine-06-reservation`.
3. Garder les modifications de plan, tests et configuration limitées au sujet; ne pas mélanger secrets ou données locales.
4. Relire le diff (`git diff --check`, puis `git diff`) et vérifier les nouveaux fichiers avant de committer.
5. Utiliser des messages de commit explicites, par exemple `docs(test): define campaign strategy`.
6. Après revue, fusionner selon la politique du cours/projet; taguer une version de campagne uniquement si l'équipe le demande.
7. Consigner le SHA de la version sous test dans les rapports; ne pas déplacer un tag de campagne déjà publié.

Les commandes `git add`, `git commit` et `git tag` ne sont pas exécutées ici : le plan reste en attente de revue et le dépôt contient d'autres changements de travail.

## 2.4 Calendrier de 10 semaines

| Semaine | Thème et activités | Livrables / critère de fin |
|---|---|---|
| 1 | Découvrir les parcours; distinguer erreur/défaut/défaillance et vérification/validation; cadrer les risques | Fiche projet, périmètre et risques : [cadrage](01-cadrage-risques.md) |
| 2 | Choisir niveaux, environnements, outils, responsabilités, critères; organiser le dépôt | Cette stratégie versionnée, calendrier et conventions de preuve |
| 3 | Examiner les règles, lever les ambiguïtés et définir des critères vérifiables | [Exigences et traçabilité](03-exigences-tracabilite.md), visa des règles ouvertes |
| 4 | Partitions d'équivalence, limites, scénarios positifs/négatifs et jeux de données | [Catalogue de plus de 15 cas](04-cas-de-test-donnees.md), données fictives |
| 5 | Modéliser les transitions; vérifier droits et conduire l'exploration | [Cycle de vie et explorations](05-etats-exploration-anomalies.md), anomalies reproductibles |
| 6 | Automatiser les règles métier en AAA; analyser assertions et couverture | Au moins 5 tests unitaires exécutés avec rapport; tests actuels à confirmer |
| 7 | Tester CRUD, intégrité et échecs de persistance | Au moins 3 tests d'intégration exécutés et preuves; base isolée |
| 8 | Tester méthodes, en-têtes, JSON, statuts et autorisations | Collection/API report pour l'endpoint AJAX identifié; couverture REST complète uniquement si contrat fourni |
| 9 | Parcours navigateur puis évaluation non fonctionnelle et régression | Scénario automatisé, rapport non fonctionnel et liste de régression exécutée |
| 10 | Consolider les anomalies, réexécuter la régression, évaluer risques résiduels | [Bilan et décision argumentée](06-automatisation-bilan.md), visa final |

Ce calendrier décrit le travail à réaliser. Il ne constitue pas un historique d'exécution : les semaines 6 à 10 ne sont pas déclarées achevées sur la seule base de ce dossier.

## 2.5 Critères d'entrée

1. Révision Git (SHA/tag) et configuration test consignés.
2. Besoins fonctionnels et règles ambiguës arbitrés ou explicitement marqués bloquants.
3. Base de test disponible, migrée et réinitialisable sans toucher à des données utiles.
4. Comptes fictifs disponibles pour chaque rôle; procédure d'authentification de test fonctionnelle.
5. Cas et résultats attendus relus par le responsable métier.

## 2.6 Critères de sortie et arrêt

La recommandation **GO** nécessite, au minimum :

- tous les cas de priorité P1 exécutés et réussis;
- aucun défaut ouvert de sévérité S1/S2, sauf acceptation documentée par le responsable habilité;
- aucune incohérence de stock/prix après réservation, annulation et relecture depuis la base;
- tests unitaires, intégration, HTTP/API et parcours d'interface convenus exécutés sur la même révision;
- couverture exigences–tests sans exigence critique orpheline;
- régression réussie après la dernière correction;
- limites, anomalies acceptées et risques résiduels signés.

Arrêter la campagne si elle cible la mauvaise base, si les fixtures touchent des données réelles ou si une dépendance métier empêche d'établir le résultat attendu. Le résultat est alors **Bloqué**, jamais « réussi » par défaut.
