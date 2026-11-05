# Dossier de test — WASSALNI

- **Version :** 1.1 (plan de test)
- **Date du dossier :** 5 novembre 2026
- **Périmètre :** application web de covoiturage Symfony
- **Statut :** dossier de préparation et de campagne; décision de livraison non prononcée faute d'exécution vérifiable des tests dans l'environnement disponible.

## Contenu

1. [Cadrage, fiche projet et risques](01-cadrage-risques.md)
2. [Stratégie, calendrier et critères de campagne](02-strategie-calendrier.md)
3. [Exigences et matrice de traçabilité](03-exigences-tracabilite.md)
4. [Catalogue de cas de test et données](04-cas-de-test-donnees.md)
5. [Cycle de vie, exploration et anomalies à confirmer](05-etats-exploration-anomalies.md)
6. [Automatisation, rapports et décision de livraison](06-automatisation-bilan.md)

## Résumé exécutif

WASSALNI permet à des passagers et conducteurs de gérer des trajets et réservations, et aux utilisateurs de soumettre des réclamations. Le code examiné contient aussi des rôles administrateur, conducteur et passager, ainsi qu'un traitement administratif des réclamations.

Le dossier couvre les parcours d'inscription/connexion, publication et recherche de trajet, réservation/annulation, réclamation et administration. Une route JSON de réservation existe (`POST /reservation/new-ajax`); aucun contrat d'API REST général n'a été identifié dans le périmètre examiné. Le volet API est donc limité à cette route tant qu'une API REST distincte n'est pas confirmée.

Le dépôt contient **7 tests unitaires** dans `tests/Service/ReservationServiceTest.php`. Leur exécution n'a pas pu être confirmée : la commande `php bin/phpunit` a échoué parce que PHP n'est pas installé/disponible dans l'environnement; le moteur Docker n'était pas joignable non plus. Aucun résultat de test, taux de couverture, test d'intégration, test d'interface ou mesure de performance n'est présenté comme réussi.

**Décision à ce stade : livraison non validée (NO-GO probatoire).** Il s'agit d'une décision sur l'insuffisance des preuves de test, et non de l'affirmation que l'application est défaillante. La campagne et les critères de décision figurent dans [le bilan](06-automatisation-bilan.md).

## Utilisation

Exécuter les cas dans l'ordre des dépendances de la stratégie, consigner le résultat réel pour chaque identifiant (Réussi, Échoué, Bloqué ou Non exécuté), joindre une preuve anonymisée et ouvrir une anomalie pour tout écart. Ne pas remplacer les comptes ou données de test par des données personnelles réelles.
