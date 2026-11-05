# 1. Cadrage, fiche projet et risques

## 1.1 Fiche projet

| Champ | Définition |
|---|---|
| Application | WASSALNI |
| Nature | Application web de mise en relation pour le covoiturage |
| Stack observée | PHP 8.2+, Symfony 7.4, Doctrine ORM 3.6, Twig; PHPUnit 12.5 déclaré en dépendance de développement |
| Stockage/environnement | Compose déclare PostgreSQL 16 et Mailpit; la disponibilité effective de l'environnement n'a pas été vérifiée |
| Profils visibles dans le code | `ROLE_PASSAGER`, `ROLE_CONDUCTEUR`, `ROLE_ADMIN` |
| Parcours prioritaires | Recherche/publication d'un trajet, réservation/annulation, dépôt/suivi/traitement d'une réclamation |
| Responsable de test | Étudiant/testeur; validation métier par le responsable produit ou l'enseignant |
| Version testée | Révision du dépôt présente le 5 novembre 2026; commit/tag à consigner avant exécution |
| Périmètre du dossier | Tests fonctionnels, règles métier, contrôle des rôles, persistance, route JSON de réservation, interface et risques non fonctionnels |
| Hors périmètre initial | Paiement réel, tests de charge à grande échelle, audit de sécurité spécialisé, API REST non présente ou non identifiée |

### Distinction des notions

- **Erreur humaine :** action ou décision incorrecte (par exemple, une règle d'annulation oubliée lors de la spécification).
- **Défaut :** imperfection dans un artefact, par exemple un contrôle métier absent du code.
- **Défaillance :** comportement observable incorrect à l'exécution, à confirmer en reproduisant un cas.
- **Vérification :** contrôle que l'implémentation respecte ses spécifications (revue, tests unitaires, intégration).
- **Validation :** contrôle que le produit répond aux besoins des utilisateurs et peut être accepté.

Une observation de lecture de code n'est donc pas automatiquement une défaillance confirmée.

## 1.2 Périmètre fonctionnel

### Inclus

1. Authentification et séparation des droits des passagers, conducteurs et administrateurs.
2. Publication et recherche de trajets; disponibilité et statut des trajets.
3. Réservation: nombre de places, disponibilité, prix total, unicité d'une réservation active.
4. Annulation: état de la réservation et restitution des places.
5. Réclamation: formulaire, validation, propriétaire, consultation par l'administrateur, réponse et statut.
6. Persistance des relations entre utilisateurs, trajets, réservations, véhicules et réclamations.
7. Route JSON `POST /reservation/new-ajax` comme surface API actuellement identifiable.

### Exclusions et limites explicites

- L'existence d'une API REST complète, d'une documentation OpenAPI ou d'endpoints pour les autres ressources n'a pas été établie.
- Les tests réels de base de données, de navigateur, d'accessibilité et de performance restent à exécuter.
- Les règles métier non décidées (délai d'annulation, statuts intermédiaires de réclamation) sont des questions à faire valider, et non des critères inventés.

## 1.3 Cartographie des risques qualité

Échelle qualitative : probabilité (P) et impact (I), chacun de 1 à 3. Score = P × I. 1–2 : faible; 3–4 : moyen; 6–9 : élevé. La priorité indique l'ordre proposé des tests, pas une sévérité d'anomalie.

| ID | Risque | P | I | Score | Réponse de test |
|---|---|---:|---:|---:|---|
| R-01 | Sur-réservation ou stock de places incohérent après réservation/annulation | 3 | 3 | 9 — élevé | Tests limites, doubles réservations, persistance et cohérence avant/après |
| R-02 | Accès à une réclamation ou à une réservation appartenant à un autre utilisateur | 2 | 3 | 6 — élevé | Tests par rôle et par propriétaire, accès anonyme et accès croisé |
| R-03 | Une valeur de réservation invalide (nulle, négative, excessive) altère le stock ou le montant | 3 | 3 | 9 — élevé | Validation aux limites sur formulaires et route JSON |
| R-04 | Statut incohérent entre trajet/réclamation et actions réellement autorisées | 2 | 3 | 6 — élevé | Tests de transitions et clarification des règles métier |
| R-05 | Réclamation invalide, non persistée ou associée à un utilisateur incorrect | 2 | 2 | 4 — moyen | Tests de formulaire, validation et intégration DB |
| R-06 | Prix total incorrect ou places perdues lors d'un échec/retour de transaction | 2 | 3 | 6 — élevé | Assertions prix/stock et scénarios d'échec de persistance |
| R-07 | Recherche affiche des trajets invalides, passés ou complets | 2 | 2 | 4 — moyen | Partitions de filtres et données de dates/statuts |
| R-08 | Erreur d'interface dans les parcours critiques ou absence de feedback fiable | 2 | 2 | 4 — moyen | Parcours navigateur et vérification de messages/états |
| R-09 | Dégradation du temps de réponse ou défaut d'accessibilité | 1 | 2 | 2 — faible | Mesure reproductible et audit automatisé ciblé |

Les risques sont provisoires tant que le responsable métier n'a pas confirmé les règles et les priorités.

## 1.4 Criticité des anomalies

- **Critique (S1) :** perte/corruption généralisée de données ou parcours principal inutilisable sans contournement.
- **Majeure (S2) :** règle centrale fausse, incohérence financière/de capacité, ou contrôle d'accès absent sur une donnée privée.
- **Moyenne (S3) :** fonctionnalité secondaire incorrecte avec contournement acceptable.
- **Mineure (S4) :** défaut visuel ou de confort sans impact métier significatif.

La **sévérité** mesure l'impact; la **priorité** (P1–P4) mesure l'ordre de correction décidé avec le produit. Une anomalie ne reçoit une sévérité définitive qu'après reproduction.
