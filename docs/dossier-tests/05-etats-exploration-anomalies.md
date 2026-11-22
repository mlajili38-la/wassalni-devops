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

### Diagramme d'états proposé

```text
                         réponse non vide par ROLE_ADMIN
  [Création valide]                                  [Traitement enregistré]
         |                                                    |
         v                                                    v
   EN_ATTENTE --------------------------------------------> TRAITEE
         |                                                    |
         | transition vers EN_COURS : à arbitrer              +-- terminal proposé
         v                                                    |   (pas de réouverture)
      EN_COURS ---------------------------------------------->+
                 réponse non vide par ROLE_ADMIN
```

Le chemin `EN_ATTENTE → TRAITEE` est celui actuellement implémenté lors d'une réponse administrative. `EN_COURS` est mentionné comme statut dans l'entité et affiché par le template d'administration, mais aucune action observée ne permet d'y entrer. Le diagramme est donc une **proposition d'oracle de test**, pas une exigence produit approuvée.

| État initial | Action / acteur | État attendu proposé | Règle / cas |
|---|---|---|---|
| Absent | Soumettre un formulaire valide / utilisateur authentifié | `EN_ATTENTE` | Création rattachée au compte; CT-18 |
| `EN_ATTENTE` | Répondre avec un texte non vide / `ROLE_ADMIN` | `TRAITEE`, réponse et date enregistrées | Transition implémentée; CT-22 |
| `EN_ATTENTE` | Accéder au traitement / passager ou conducteur | État inchangé, accès refusé | CT-32 |
| `EN_ATTENTE` | Réponse vide ou composée d'espaces / `ROLE_ADMIN` | État inchangé | CT-33 |
| `EN_ATTENTE` | Passer à `EN_COURS` | À arbitrer | CT-23 / EX-11 |
| `EN_COURS` | Répondre / `ROLE_ADMIN` | `TRAITEE` proposée | À arbitrer avec EX-11 |
| `TRAITEE` | Répondre une nouvelle fois / `ROLE_ADMIN` | Refus proposé si état terminal | CT-34; oracle produit à confirmer |
| Tout état | Lire une réclamation d'un autre utilisateur / utilisateur non admin | Refus sans divulgation | CT-21, CT-35 |

Les statuts et transitions ne sont pas protégés par une machine à états dédiée dans le code observé. Confirmer avec le responsable produit si `TRAITEE` est terminal et si `EN_COURS` fait partie du processus avant de bloquer ou valider CT-23 et CT-34.

### Matrice des droits à vérifier

| Acteur | Créer / consulter ses réclamations | Consulter réclamation d'autrui | Lister / traiter toutes les réclamations |
|---|---|---|---|
| Anonyme | Refus attendu | Refus attendu | Refus attendu |
| Passager (`ROLE_USER`) | Autorisé | Refus attendu | Refus attendu |
| Conducteur (`ROLE_CONDUCTEUR`, également `ROLE_USER`) | Autorisé selon l'accès actuel; politique produit à confirmer | Refus attendu | Refus attendu |
| Administrateur (`ROLE_ADMIN`) | Autorisé comme utilisateur connecté | Autorisé pour le détail | Autorisé; traitement réservé à l'administration |

Les routes `/reclamation/nouvelle`, `/reclamation/mes-reclamations` et `/reclamation/voir/{id}` exigent `ROLE_USER`; le détail autorise aussi l'administrateur à consulter une réclamation qui ne lui appartient pas. Le préfixe `/admin` et le contrôleur d'administration exigent `ROLE_ADMIN`. Vérifier ces autorisations sur les URL et requêtes directes, pas seulement via les liens affichés.

## 5.3 Charte et scénarios d'exploration

Durée recommandée : 30 minutes par session. Noter la version, le rôle, l'objectif, les données, les actions exactes, le résultat observé et la preuve. Ne pas utiliser de données personnelles. Arrêter et préserver l'état dès qu'une corruption ou une fuite de données est soupçonnée.

| Session | Mission | Angles d'exploration |
|---|---|---|
| EXPL-01 | Réserver puis annuler un trajet | Limites de places, répétition, rechargement, deux sessions concurrentes, feedback |
| EXPL-02 | Explorer le cycle d'une réclamation | Soumission invalide/valide, statut initial, réponse admin, réponse vide ou espaces, répétition, relecture après actualisation; CT-18 à CT-20, CT-22 à CT-23, CT-33 à CT-34 |
| EXPL-03 | Explorer les droits par rôle | Tester liste, détail d'autrui et traitement par URL directe en anonyme, passager, conducteur et admin; vérifier absence de mutation après refus; CT-01 à CT-04, CT-21, CT-32, CT-35 |

#### Script guidé EXPL-02

1. Avec `PASSAGER_A`, soumettre une réclamation valide, d'abord sans champs optionnels puis avec un trajet/conducteur fictif; vérifier propriétaire, `EN_ATTENTE` et visibilité dans la liste.
2. Tenter un message vide puis un message inférieur à la limite; vérifier qu'aucune réclamation supplémentaire n'est créée.
3. Avec `ADMIN_A`, répondre avec un texte valide; actualiser et vérifier statut, réponse et date.
4. Répéter avec une réclamation de test distincte et une réponse composée d'espaces; relever si le formulaire, le serveur ou les deux refusent la saisie.
5. Si le produit autorise une action `EN_COURS`, l'exécuter et noter chaque transition. Sinon, ne pas modifier la base manuellement pour simuler un parcours utilisateur; marquer CT-23 bloqué et consigner la décision produit manquante.
6. Sur une réclamation traitée de test, envoyer une seconde réponse, relever l'état HTTP, le feedback et les valeurs persistées. Ne pas conclure à un défaut avant confirmation de la règle de réouverture.

#### Script guidé EXPL-03

Pour chaque rôle, ouvrir les pages via l'interface puis saisir directement les URL et envoyer les requêtes correspondantes. Après chaque refus, relire la réclamation avec `ADMIN_A` et comparer statut, réponse et date. Avec `PASSAGER_B`, ouvrir le détail de `PASSAGER_A` puis sa propre liste; vérifier que le détail est refusé et que sa liste n'inclut pas les réclamations de `PASSAGER_A`.

## 5.4 Observations statiques à reproduire

Les entrées ci-dessous sont des **pistes issues de la lecture du code**, pas des anomalies confirmées en exécution. Elles doivent être reproduites sur une base de test et comparées à une règle métier validée avant d'être enregistrées comme défauts.

| ID de suivi | Observation | Cas de vérification | État |
|---|---|---|---|
| OBS-01 | Le contrôleur de réservation HTML vérifie la disponibilité mais ne vérifie pas explicitement que la quantité est strictement positive; l'entité porte une contrainte `Positive`. | CT-12 sur la voie HTML, vérifier validation effective, persistance et stock | À reproduire; sévérité non attribuée |
| OBS-02 | La voie JSON vérifie disponibilité et unicité; le contrôle explicite de quantité positive n'est pas visible dans la route. | CT-12, CT-28, vérifier que les deux voies sont cohérentes | À reproduire; sévérité non attribuée |
| OBS-03 | La méthode HTML de réservation ne montre pas de contrôle explicite du statut `VALIDE`, alors que la voie JSON le fait. | CT-05 et réservation directe d'un trajet non validé via CT-13/CT-28 | À reproduire; règle à confirmer |
| OBS-04 | La règle d'annulation à deux heures est disponible dans une méthode de l'entité; le contrôleur d'annulation consulté ne montre pas son appel. | CT-17 | À reproduire après confirmation de EX-08 |
| OBS-05 | Les statuts annoncés pour une réclamation incluent `EN_COURS`; le flux admin visible passe directement de la réponse à `TRAITEE`. | CT-23 | Ambiguïté produit, pas nécessairement un défaut |

## 5.5 Premières fiches d'anomalie (brouillons à reproduire)

Ces fiches sont des **candidats issus de l'inspection statique**. Elles ne constituent pas des défauts confirmés : l'environnement, la version testée, les preuves d'exécution et la sévérité restent à renseigner.

### ANO-01 — Statut `EN_COURS` sans transition utilisateur/admin identifiée

| Champ | Valeur |
|---|---|
| Type / état | Ambiguïté de spécification — à arbitrer, non confirmée |
| Référence | CT-23, EX-11 |
| Préconditions | Réclamation de test `EN_ATTENTE`; compte `ADMIN_A` |
| Étapes de reproduction | Ouvrir la gestion des réclamations; examiner les actions disponibles; tenter de faire passer la réclamation à `EN_COURS` par le parcours normal |
| Résultat attendu | À définir par le produit : soit une action autorisée vers `EN_COURS`, soit absence documentée de ce statut dans le cycle |
| Observation statique | Le statut est déclaré dans l'entité et rendu dans le template; aucune action dédiée pour l'atteindre n'a été identifiée |
| Preuve / fréquence / sévérité / priorité | À renseigner après décision et exécution |
| Statut | Bloqué par arbitrage produit; ne pas classer comme défaut avant décision |

### ANO-02 — Une réclamation traitée peut potentiellement être retraitée

| Champ | Valeur |
|---|---|
| Type / état | Défaut potentiel de transition — à reproduire |
| Référence | CT-34 |
| Préconditions | Réclamation `TRAITEE` avec réponse/date connues; session `ADMIN_A`; règle confirmant `TRAITEE` terminal |
| Étapes de reproduction | Envoyer une seconde réponse à `/admin/reclamation/traiter/{id}`; relire l'enregistrement |
| Résultat attendu | Si l'état est terminal : refus de la transition, réponse et date initiales inchangées |
| Observation statique | Le contrôleur affecte réponse, statut et date dès qu'une réponse truthy est fournie; aucun contrôle de l'état initial n'est visible |
| Preuve / fréquence / sévérité / priorité | Non exécuté; à renseigner |
| Statut | À reproduire après validation de la règle terminale |

### ANO-03 — Une réponse composée d'espaces peut être acceptée

| Champ | Valeur |
|---|---|
| Type / état | Défaut potentiel de validation — à reproduire |
| Référence | CT-33 |
| Préconditions | Réclamation `EN_ATTENTE`; session `ADMIN_A` |
| Étapes de reproduction | Soumettre une réponse contenant uniquement des espaces depuis le formulaire ou une requête contrôlée; vérifier statut, réponse et date persistés |
| Résultat attendu | Réponse considérée vide après suppression des espaces; aucune transition |
| Observation statique | Le contrôleur vérifie la vérité PHP de la valeur mais ne la normalise pas avant le test; une chaîne d'espaces est truthy |
| Preuve / fréquence / sévérité / priorité | Non exécuté; à renseigner |
| Statut | À reproduire; défaut confirmé uniquement si l'exécution montre une transition |

Pour toute anomalie confirmée, compléter révision, environnement, résultat observé, preuve anonymisée, fréquence, sévérité et priorité. Ne pas déclarer « corrigé » avant réexécution du cas et de la régression liée.
