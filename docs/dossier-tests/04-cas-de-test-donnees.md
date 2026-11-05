# 4. Catalogue de cas de test et jeux de données

## 4.1 Conventions de préparation

Utiliser des données fictives et recréables. Pour les tests de stock, noter le nombre initial, l'opération et le nombre final. Vérifier les résultats depuis l'interface et/ou la base, et isoler chaque cas qui modifie les données.

### Jeu de données minimal

| Alias | Données |
|---|---|
| `PASSAGER_A` / `PASSAGER_B` | Deux comptes actifs distincts, rôle passager |
| `CONDUCTEUR_A` | Compte fictif actif, rôle conducteur, véhicule `VEHICULE_A` |
| `ADMIN_A` | Compte fictif, rôle administrateur |
| `TRAJET_LIBRE` | Trajet futur, `VALIDE`, 3 places disponibles, prix 10,00 (devise à confirmer) |
| `TRAJET_COMPLET` | Trajet futur, `VALIDE`, 0 place disponible |
| `TRAJET_ATTENTE` | Trajet futur non `VALIDE` |
| `RESERVATION_A` | Réservation confirmée de `PASSAGER_A` sur `TRAJET_LIBRE` |
| `RECLAMATION_A` | Réclamation d'`PASSAGER_A`, sujet valide, message de 10 caractères ou plus, `EN_ATTENTE` |

Ne pas encoder de mot de passe réel dans les fixtures. Les identifiants doivent être créés par la configuration de test ou injectés dans des variables locales ignorées par Git.

## 4.2 Cas de test

Pour tous les cas, enregistrer : version/SHA, environnement, date, exécutant, résultat réel, preuve anonymisée et anomalie liée si échec.

### Accès et recherche

| ID | Préconditions et données | Étapes | Résultat attendu |
|---|---|---|---|
| CT-01 | Session anonyme | Ouvrir mes réservations, nouvelle réclamation et zone conducteur | Redirection vers authentification ou refus HTTP approprié; aucune donnée privée affichée |
| CT-02 | Session `PASSAGER_A` | Accéder à une fonction exclusivement conducteur puis à l'administration | Refus d'accès; aucune mutation |
| CT-03 | Session `CONDUCTEUR_A` | Accéder à l'administration et à une fonction de réservation passager | Refus d'accès si la fonction est réservée au rôle correspondant |
| CT-04 | Session `ADMIN_A` | Ouvrir l'espace admin; tenter d'utiliser un formulaire destiné au passager | Zone admin disponible; fonction passager soumise aux règles produit, sans privilège indu |
| CT-05 | `TRAJET_LIBRE`, `TRAJET_COMPLET`, `TRAJET_ATTENTE` | Rechercher sans filtre | Seuls les trajets validés et ayant des places sont présentés |
| CT-06 | Trajets fictifs de départ/destination différents | Filtrer par départ, puis destination, puis les deux | Chaque résultat satisfait tous les filtres soumis |
| CT-07 | Trajets datés la veille, le jour choisi et le lendemain | Rechercher sur la date choisie | Seuls les trajets dans la journée demandée sont listés; aucune erreur de parsing |

### Publication, réservation et annulation

| ID | Préconditions et données | Étapes | Résultat attendu |
|---|---|---|---|
| CT-08 | `CONDUCTEUR_A` possède `VEHICULE_A` | Publier un trajet avec villes, dates futures cohérentes et prix positif | Trajet créé avec conducteur/véhicule corrects, disponibilité initiale attendue et feedback de succès |
| CT-09 | Conducteur sans véhicule ou formulaire incomplet | Ouvrir le formulaire/publication puis soumettre avec véhicule absent ou dates/prix invalides | Publication refusée avec feedback; aucune ligne trajet créée |
| CT-10 | `TRAJET_LIBRE` à 3 places et tarif 10,00 | `PASSAGER_A` réserve 2 places | Réservation `CONFIRMEE`, total 20,00, stock réduit de 3 à 1 |
| CT-11 | `TRAJET_LIBRE` à 3 places | Réserver exactement 3 places | Réservation acceptée; stock égal à zéro, sans valeur négative |
| CT-12 | Trajet à 3 places | Soumettre 0 place puis une valeur négative par chaque voie de réservation | Demande refusée; aucune réservation créée; stock et total inchangés |
| CT-13 | `TRAJET_LIBRE` à 3 places | Demander 4 places | Demande refusée; réservation absente; stock reste à 3 |
| CT-14 | `PASSAGER_A` possède déjà une réservation confirmée sur le trajet | Soumettre une deuxième réservation sur le même trajet | Refus selon la règle d'unicité; première réservation et stock restent cohérents |
| CT-15 | Réservation confirmée possédée par `PASSAGER_A` | Annuler comme `PASSAGER_A` | Statut annulé, date d'annulation enregistrée, places rendues une seule fois |
| CT-16 | Réservation appartenant à `PASSAGER_A`; connexion `PASSAGER_B` | Tenter d'afficher ou d'annuler la réservation | Refus d'accès; état et stock inchangés |
| CT-17 | Trajet dont le départ est à plus de deux heures, exactement deux heures, puis moins de deux heures | Tenter l'annulation à chaque borne | Appliquer la règle validée par le produit; consigner toute divergence entre règle convenue et application |

### Réclamations

| ID | Préconditions et données | Étapes | Résultat attendu |
|---|---|---|---|
| CT-18 | Session `PASSAGER_A` | Soumettre sujet valide et message valide, sans trajet optionnel | Réclamation créée en `EN_ATTENTE`, rattachée à `PASSAGER_A`, puis visible dans sa liste |
| CT-19 | Session `PASSAGER_A` | Soumettre le formulaire avec message vide | Validation refusée; pas de réclamation créée |
| CT-20 | Session `PASSAGER_A` | Soumettre un message de 9 caractères, puis de 10 caractères | 9 refusé; 10 accepté si tous les autres champs sont valides |
| CT-21 | `RECLAMATION_A` appartient à `PASSAGER_A`; session `PASSAGER_B` | Tenter d'ouvrir son URL de détail | Refus d'accès; aucune information de la réclamation n'est révélée |
| CT-22 | Session `ADMIN_A` et `RECLAMATION_A` en attente | Répondre avec un texte valide puis rouvrir la réclamation | Réponse persistée, statut traité, date de traitement cohérente |
| CT-23 | Réclamation en attente | Examiner les actions disponibles et tenter le passage par `EN_COURS` | Résultat attendu uniquement après arbitrage de EX-11; à défaut, marquer Bloqué |

### Persistance et surface JSON

| ID | Préconditions et données | Étapes | Résultat attendu |
|---|---|---|---|
| CT-24 | Base de test migrée; `TRAJET_LIBRE` | Réserver, relire la réservation et le trajet depuis le repository après rechargement | Réservation, prix, relations et stock persistent correctement |
| CT-25 | Base dédiée et mécanisme de panne contrôlé | Simuler un échec au moment de la persistance/flush | Aucune réservation partielle ni décrément de stock non persisté; transaction cohérente |
| CT-26 | Session HTTP authentifiée, trajet valide | `POST /reservation/new-ajax` JSON avec `trajetId` et `nbPlaces` valides | Réponse JSON de succès; statut HTTP 2xx; identifiant et montant cohérents; stock décrémenté |
| CT-27 | Session authentifiée | Envoyer JSON invalide, identifiant absent puis identifiant inconnu | Réponse d'erreur documentée (la route distingue notamment identifiant absent et trajet introuvable); aucun effet de bord |
| CT-28 | Session authentifiée et trajet non validé, complet puis demande de places invalides | Appeler la route AJAX pour chaque scénario | Erreur JSON et code non-2xx cohérents; aucune réservation ni modification de stock |

### Interface et qualité non fonctionnelle

| ID | Préconditions et données | Étapes | Résultat attendu |
|---|---|---|---|
| CT-29 | Navigateur cible, `PASSAGER_A`, `TRAJET_LIBRE` | Se connecter, rechercher un trajet, réserver et ouvrir mes réservations | Parcours complet sans erreur visible; données affichées cohérentes avec la persistance |
| CT-30 | Pages de recherche, réservation et réclamation | Vérifier navigation clavier, libellés, focus et messages d'erreur; exécuter un outil d'accessibilité choisi | Problèmes bloquants consignés; aucune conformité WCAG affirmée sans audit complet |
| CT-31 | Environnement de test stable et charge convenue | Mesurer un scénario de recherche et de réservation avec concurrence contrôlée | Rapport indiquant charge, durée, percentiles, erreurs et limites; seuil à faire approuver avant mesure |

## 4.3 Priorité d'exécution recommandée

1. CT-01 à CT-04 (rôles et confidentialité).
2. CT-10 à CT-17 (intégrité du stock et cycle de réservation).
3. CT-18 à CT-23 (propriété et traitement des réclamations).
4. CT-05 à CT-09 (trajets/recherche).
5. CT-24 à CT-28 (persistance et JSON).
6. CT-29 à CT-31 (interface et non fonctionnel).

Les cas CT-17, CT-23 et CT-31 sont bloqués tant que leur règle/seuil n'est pas défini.
