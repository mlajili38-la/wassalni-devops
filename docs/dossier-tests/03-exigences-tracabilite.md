# 3. Exigences et matrice de traçabilité

## 3.1 Règles examinées et points à faire valider

Les critères ci-dessous sont des critères de test proposés à partir des fonctions observées. Les règles marquées **À confirmer** ne sont pas des décisions produit déjà acquises.

| ID | Exigence / critère d'acceptation vérifiable | Source / état |
|---|---|---|
| EX-01 | Un utilisateur non authentifié ne peut pas accéder aux fonctions protégées par un rôle; un passager, un conducteur et un administrateur ne peuvent pas exécuter une action réservée à un autre rôle. | Contrôleurs et configuration de sécurité observés; matrice de routes à vérifier en exécution |
| EX-02 | La recherche ne retourne que les trajets de statut `VALIDE` et avec des places disponibles; les filtres départ, destination et date restreignent les résultats. | `TrajetController::search`; critères à vérifier via intégration |
| EX-03 | La publication d'un trajet requiert un conducteur et un véhicule; les données obligatoires et les dates/prix invalides sont refusés. | Validation et contrôles visibles; règle de dates à confirmer sur les bornes |
| EX-04 | Une réservation valide associe le passager au trajet, conserve le statut `CONFIRMEE`, calcule le prix et décrémente le stock du nombre réservé. | Service et contrôleur observés |
| EX-05 | Le nombre de places réservé doit être strictement positif et inférieur ou égal au disponible; le stock ne devient jamais négatif. | Critère nécessaire; contrôle exact à valider sur chaque entrée et route |
| EX-06 | Un passager ne peut pas avoir plus d'une réservation confirmée pour le même trajet. | Service et route AJAX incluent cette règle; route web à vérifier |
| EX-07 | Seul le propriétaire d'une réservation peut la consulter/annuler; l'annulation confirmée rend une seule fois les places et met l'état à `ANNULEE`. | Contrôleur observé; cohérence transactionnelle à vérifier |
| EX-08 | Une annulation n'est-elle permise que jusqu'à deux heures avant le départ? | **À confirmer** : règle définie dans `Reservation::peutEtreAnnulee()`, mais non vérifiée par le contrôleur d'annulation examiné |
| EX-09 | Une réclamation exige un sujet et un message d'au moins 10 caractères, appartient à l'utilisateur authentifié et commence en `EN_ATTENTE`. | Entité, formulaire et contrôleur observés |
| EX-10 | Un utilisateur ne consulte que ses propres réclamations; un administrateur peut consulter les réclamations et enregistrer une réponse. | Contrôleurs observés; refus croisé à confirmer par test |
| EX-11 | Les statuts de réclamation sont-ils `EN_ATTENTE → EN_COURS → TRAITEE`, ou un traitement direct en `TRAITEE` suffit-il? | **À confirmer** : le code déclare `EN_COURS`, mais l'action administrative examinée traite directement une réclamation |
| EX-12 | Une erreur de validation ou de persistance ne laisse ni réservation/réclamation partielle ni stock modifié de façon incorrecte. | Critère d'intégrité à démontrer par tests d'intégration |
| EX-13 | La route JSON de réservation renvoie un statut HTTP cohérent, un JSON lisible et ne crée pas de réservation si la demande est invalide. | Route `/reservation/new-ajax`; contrat JSON complet à formaliser |
| EX-14 | La monnaie, le fuseau horaire et les bornes exactes de date/annulation sont consistants entre formulaire, serveur et affichage. | **À confirmer** par produit et environnement |

## 3.2 Matrice exigences–cas de test

| Exigence | Cas couvrant |
|---|---|
| EX-01 | CT-01, CT-02, CT-03, CT-04, CT-14, CT-18 |
| EX-02 | CT-05, CT-06, CT-07 |
| EX-03 | CT-08, CT-09 |
| EX-04 | CT-10, CT-11 |
| EX-05 | CT-12, CT-13 |
| EX-06 | CT-14 |
| EX-07 | CT-15, CT-16 |
| EX-08 | CT-17 |
| EX-09 | CT-18, CT-19, CT-20 |
| EX-10 | CT-18, CT-21, CT-22 |
| EX-11 | CT-23 |
| EX-12 | CT-24, CT-25 |
| EX-13 | CT-26, CT-27, CT-28 |
| EX-14 | CT-08, CT-09, CT-17 |

Les cas du catalogue sont des spécifications à exécuter. Ils ne sont pas tous automatisés à ce jour.

## 3.3 Ambiguïtés à résoudre avant acceptation

1. Délai limite d'annulation et comportement à exactement deux heures du départ.
2. Nombre minimum/maximum de places autorisé par réservation et traitement d'une valeur absente.
3. Statuts de réclamation attendus et autorité habilitée à passer à `EN_COURS`.
4. Si l'absence de réponse d'un administrateur permet une transition de statut.
5. Contrat attendu pour la surface API : endpoint AJAX seulement ou API REST versionnée.
6. Un conducteur peut-il réserver son propre trajet? La règle n'est pas établie dans les fichiers examinés.
7. Règles d'arrondi, devise et timezone pour le total et les dates.

Chaque réponse doit être datée et attribuée au décideur avant que les cas concernés ne soient déclarés « réussis ».
