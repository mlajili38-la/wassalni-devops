# 6. Automatisation, rapports et décision de livraison

## 6.1 État de l'automatisation constaté

- Le dépôt déclare PHPUnit 12.5 et une suite qui découvre les tests sous `tests/`.
- `tests/Service/ReservationServiceTest.php` contient **7 tests unitaires** portant sur la création, le décrément des places, l'insuffisance de places, la réservation déjà existante, l'annulation, la restitution et le refus d'une annulation répétée.
- Arrange–Act–Assert, assertions, stub et mock sont déjà illustrés dans ce fichier.
- Les sept tests n'ont pas pu être exécutés ici : `php bin/phpunit` échoue car la commande `php` n'est pas disponible dans cet environnement Windows. `docker compose ps` échoue également car le moteur Docker Desktop n'est pas joignable.
- Il n'y a pas de preuve disponible ici d'une suite d'intégration, d'une collection Postman/Bruno, d'un test Playwright/Cypress/Selenium ou d'un rapport de couverture. Leur absence de preuve ne démontre pas qu'ils n'existent pas ailleurs.

### Procédure de relance

Depuis la racine du dépôt, après installation/configuration d'un PHP compatible et d'une base exclusivement réservée aux tests :

```powershell
php bin/phpunit
```

Consigner la version PHP, le SHA Git, le résultat de la commande et les erreurs complètes. Pour une campagne d'intégration, démarrer et vérifier les services requis, appliquer les migrations sur une base de test dédiée, puis exécuter la suite. Ne pas utiliser une URL de base de production.

### AAA attendu

1. **Arrange :** créer les entités et doubles nécessaires; fixer l'état initial et les nombres de places.
2. **Act :** appeler une seule action métier.
3. **Assert :** vérifier l'état, les effets de bord, les exceptions et, pour une intégration, la relecture persistée.

La suite unitaire actuelle couvre déjà plusieurs résultats positifs et négatifs du service de réservation. Les cas limites manquants doivent être ajoutés au code de test et exécutés avant de conclure à la couverture du besoin.

## 6.2 Rapport d'exécution initial

| Vérification | Résultat | Preuve / commentaire |
|---|---|---|
| Inspection de la suite PHPUnit | 7 méthodes de test visibles dans le fichier de service | Lecture du dépôt; ne prouve pas qu'elles passent |
| `php bin/phpunit` | **Bloqué / non exécuté** | Échec de lancement : commande `php` introuvable |
| Disponibilité Docker | **Bloqué** | Moteur Docker Desktop non joignable |
| Tests intégration/DB | Non exécutés | Aucun runtime de test disponible pour cette campagne |
| Tests HTTP/API | Non exécutés | À faire sur l'environnement Symfony dédié |
| Test interface | Non exécuté | Navigateur/outillage non configuré dans cette campagne |
| Accessibilité/performance | Non exécutés | Seuils et environnement de mesure à convenir |
| Couverture de code | Non mesurée | Ne pas communiquer de pourcentage sans rapport généré |

## 6.3 Fiche de résultat à copier par cas

```text
Identifiant :
Version / SHA :
Environnement :
Préconditions / données anonymisées :
Résultat attendu :
Résultat observé :
Statut : Réussi | Échoué | Bloqué | Non exécuté
Preuve :
Anomalie liée :
Exécutant / date :
```

## 6.4 Régression minimale

Après chaque correction concernant réservation, réexécuter CT-10 à CT-17 et CT-24 à CT-28 selon la surface touchée. Après correction des rôles/propriétaires, réexécuter CT-01 à CT-04, CT-16 et CT-21. Après correction des réclamations, réexécuter CT-18 à CT-23. Toute régression doit cibler la même révision candidate de livraison.

## 6.5 Bilan de campagne et décision

### Synthèse au 5 novembre 2026

| Mesure | État |
|---|---|
| Cas spécifiés | 31 dans le catalogue |
| Tests unitaires observés | 7 dans `ReservationServiceTest.php` |
| Tests exécutés avec preuve disponible | 0 dans cette campagne |
| Cas échoués confirmés | Aucun; l'absence d'exécution empêche de conclure |
| Anomalies confirmées | Aucune par exécution dans cette campagne |
| Observations à reproduire | OBS-01 à OBS-05 |
| Exigences ambiguës bloquantes | Délai d'annulation, statuts de réclamation, contrat API, bornes de données |

### Décision argumentée

**NO-GO probatoire / décision de livraison différée.** La version ne peut pas être déclarée validée : la suite existante n'a pas été exécutée, les tests DB/HTTP/interface et non fonctionnels manquent de preuves dans cette campagne, plusieurs exigences demeurent à arbitrer, et des observations statiques sur les réservations restent à reproduire.

Cette décision n'affirme pas que les tests échoueraient. Elle signifie que les critères de sortie ne sont pas démontrés. Un **GO** pourra être proposé après exécution sur une révision identifiée, réussite des cas prioritaires, résolution/acceptation formelle des anomalies majeures, clôture des ambiguïtés et visa de recette.

### Visa de livraison

| Décisionnaire | Décision | Révision (SHA/tag) | Date | Réserves acceptées / signature |
|---|---|---|---|---|
| Responsable produit / enseignant | En attente | À renseigner | À renseigner | À renseigner |
| Responsable campagne | NO-GO probatoire | À renseigner | 5 novembre 2026 | Campagne d'exécution non réalisée dans l'environnement disponible |
