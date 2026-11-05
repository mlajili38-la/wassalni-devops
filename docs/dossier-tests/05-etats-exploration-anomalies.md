# 5. Cycle de vie, exploration et anomalies à confirmer

## 5.1 Cycle de vie des réservations

```text
                annuler (propriétaire, règle de délai à confirmer)
CONFIRMEE ------------------------------------------------------> ANNULEE
     |                                                               |
     +-- réservation active: places décrémentées                      +-- annulation répétée refusée
     +-- une seule réservation confirmée par passager/trajet          +-- aucune seconde restitution
```

Le trajet doit rester cohérent avec le nombre de places réellement disponibles. Les événements de création et d'annulation sont à vérifier aussi après relecture depuis la base. Le délai de deux heures apparaît dans `Reservation::peutEtreAnnulee()`; l'action d'annulation observée doit être testée contre la décision métier.

## 5.2 Cycle de vie des réclamations

```text
Création par l'utilisateur
          |
          v
     EN_ATTENTE ---------> TRAITEE
          |                 réponse admin + date de traitement
          |
          +----> EN_COURS ----> TRAITEE   (transition attendue à confirmer)
```

L'entité annonce `EN_ATTENTE`, `EN_COURS` et `TRAITEE`. Le contrôleur administratif observé enregistre une réponse et passe directement à `TRAITEE`. L'existence d'un statut `EN_COURS` dans le code ne suffit pas à en faire une exigence acceptée : EX-11 doit être décidée avant la recette.

## 5.3 Charte d'exploration

Durée recommandée : 30 minutes par session. Noter la version, le rôle, l'objectif, les données, les actions exactes, le résultat observé et la preuve. Ne pas utiliser de données personnelles. Arrêter et préserver l'état dès qu'une corruption ou une fuite de données est soupçonnée.

| Session | Mission | Angles d'exploration |
|---|---|---|
| EXPL-01 | Réserver puis annuler un trajet | Limites de places, répétition, rechargement, deux sessions concurrentes, feedback |
| EXPL-02 | Soumettre et suivre une réclamation | Champs vides/limites, sélection facultative de trajet/conducteur, propriétaire, réponse admin |
| EXPL-03 | Parcourir les fonctions par rôle | Ouvrir les URL directement, navigation retour, session expirée, changement de compte |

## 5.4 Observations statiques à reproduire

Les entrées ci-dessous sont des **pistes issues de la lecture du code**, pas des anomalies confirmées en exécution. Elles doivent être reproduites sur une base de test et comparées à une règle métier validée avant d'être enregistrées comme défauts.

| ID de suivi | Observation | Cas de vérification | État |
|---|---|---|---|
| OBS-01 | Le contrôleur de réservation HTML vérifie la disponibilité mais ne vérifie pas explicitement que la quantité est strictement positive; l'entité porte une contrainte `Positive`. | CT-12 sur la voie HTML, vérifier validation effective, persistance et stock | À reproduire; sévérité non attribuée |
| OBS-02 | La voie JSON vérifie disponibilité et unicité; le contrôle explicite de quantité positive n'est pas visible dans la route. | CT-12, CT-28, vérifier que les deux voies sont cohérentes | À reproduire; sévérité non attribuée |
| OBS-03 | La méthode HTML de réservation ne montre pas de contrôle explicite du statut `VALIDE`, alors que la voie JSON le fait. | CT-05 et réservation directe d'un trajet non validé via CT-13/CT-28 | À reproduire; règle à confirmer |
| OBS-04 | La règle d'annulation à deux heures est disponible dans une méthode de l'entité; le contrôleur d'annulation consulté ne montre pas son appel. | CT-17 | À reproduire après confirmation de EX-08 |
| OBS-05 | Les statuts annoncés pour une réclamation incluent `EN_COURS`; le flux admin visible passe directement de la réponse à `TRAITEE`. | CT-23 | Ambiguïté produit, pas nécessairement un défaut |

Pour convertir une observation en anomalie, créer un ticket avec titre, révision, environnement, préconditions, étapes, résultat attendu, résultat observé, preuve anonymisée, fréquence, sévérité et priorité. Ne pas déclarer « corrigé » avant réexécution du cas et de la régression liée.
