# Qualité logicielle et tests automatisés (Lot 3)

## 1. Vue d'ensemble

Tous les contrôles s'exécutent dans le job CI **`Quality, tests and dependency audit`**
(`.github/workflows/ci.yml`), à chaque push sur `main` et à chaque Pull Request.
Chaque étape est **bloquante** : si elle échoue, le job échoue, le job `container`
(build, déploiement de test, publication de l'image) ne s'exécute pas et la PR ne peut pas être fusionnée.

| Ordre | Étape CI | Outil | Échoue si… |
|---|---|---|---|
| 1 | Check PHP syntax | `php -l` | erreur de syntaxe PHP |
| 2 | Check code style | PHP-CS-Fixer (`@Symfony`) | un fichier de `src/` ou `tests/` n'est pas formaté |
| 3 | Static analysis | PHPStan niveau 5 + extensions Symfony/Doctrine | une **nouvelle** erreur apparaît (hors baseline) |
| 4 | Validate Symfony container | `lint:container` | service mal configuré |
| 5 | Audit dependencies | `composer audit` | vulnérabilité connue dans une dépendance |
| 6 | Prepare test database | migrations Doctrine sur MySQL 8.0 | migration invalide ou schéma désynchronisé |
| 7 | Run tests with coverage | PHPUnit 12 + PCOV | un test échoue |
| 8 | Coverage quality gate | `scripts/check-coverage.php` | couverture des lignes < `COVERAGE_MIN` (10 %) |

Rapports archivés (artefact **`test-reports-<sha>`** de chaque exécution) :
`junit.xml` (résultats), `testdox.txt` (lisible), `clover.xml` (couverture), `coverage-html/` (rapport navigable).

## 2. Lancer les contrôles en local (PowerShell)

```powershell
$env:PATH = "C:\wamp64\bin\php\php8.3.14;$env:PATH"   # PATH de la session uniquement

vendor/bin/php-cs-fixer check        # vérifier le style
vendor/bin/php-cs-fixer fix          # corriger automatiquement le style
php bin/console cache:warmup --env=dev
vendor/bin/phpstan analyse           # analyse statique

# Tests : nécessitent un MySQL. La base de test "wassalni_test" est distincte de la base de dev.
php bin/console doctrine:database:create --if-not-exists --env=test
php bin/console doctrine:migrations:migrate -n --env=test
vendor/bin/phpunit                   # toutes les suites
vendor/bin/phpunit --testsuite unit  # tests unitaires seuls (sans base)
$env:XDEBUG_MODE = "coverage"; vendor/bin/phpunit --coverage-html build/reports/coverage-html
```

## 3. Tests

| Suite | Dossier | Base de données | Contenu |
|---|---|---|---|
| `unit` | `tests/Controller`, `tests/Service` | aucune (stubs/mocks) | `ReservationService` (7 cas), `HealthController` |
| `integration` | `tests/Integration` | **MySQL `wassalni_test`** | réservation et annulation réelles, double réservation refusée par une vraie requête, unicité de l'email, contraintes de validation, requêtes HTTP (`/health`, pages publiques, redirection d'une page protégée) |

**Base de test distincte** : en environnement `test`, `config/packages/doctrine.yaml` ajoute le suffixe `_test`
au nom de la base. Les tests n'utilisent donc jamais la base de développement ; un garde-fou dans `DatabaseTestCase` fait échouer le test si la base ne se termine pas par `_test`. Chaque test d'intégration
s'exécute dans une transaction **annulée** à la fin (`tests/Integration/DatabaseTestCase.php`) : la base reste propre.
En CI, la base est un service MySQL 8.0 jetable, créé pour le job puis détruit.

## 4. Analyse statique : baseline PHPStan

À l'introduction de PHPStan (niveau 5), toutes les erreurs signalées sur le code existant ont été examinées :

- **3 vrais bugs corrigés** (chacun provoquait une erreur 500) :
  - `SecurityController` : import `RegistrationType` manquant → page `/inscription` ;
  - `AvisController` : import `User` manquant → page `/avis/conducteur/{id}` ;
  - `Vehicule` : `Assert\Range(message: …)`, argument inexistant en Symfony 7 → `notInRangeMessage`.
- Un **4e bug** du même type (`Assert\Length(message: …)` dans `User`) a été révélé par le test d'intégration de `/inscription`, puis corrigé.
- Les **58 erreurs restantes** sont gelées dans `phpstan-baseline.neon`. Ce sont surtout des écarts de typage
  entre les propriétés nullables des entités et les colonnes `NOT NULL` (45), et des appels sur `UserInterface`
  sans vérifier le type exact (`getUser()`). Elles ne bloquent pas le pipeline ; **toute nouvelle erreur le bloque**.
  Objectif d'amélioration : réduire la baseline à chaque PR.

## 5. Couverture

Couverture actuelle : **11,45 % des lignes** (163/1424), 25,8 % des méthodes.
Elle est faible parce que les contrôleurs volumineux (administration, trajets, tableaux de bord) n'ont pas de tests.
Le service métier `ReservationService` et le health check sont couverts.
Le seuil `COVERAGE_MIN` est fixé à **10 %** pour **empêcher toute régression** ; il est à relever au fur et
à mesure de l'ajout de tests (prochaine cible : tests HTTP authentifiés des contrôleurs de réservation).

## 6. Anomalies connues (non corrigées)

| Anomalie | Impact | Décision |
|---|---|---|
| `UserRepository::findByRoles()` utilise `JSON_CONTAINS`, fonction inconnue de Doctrine DQL → `QueryException` | Aucun : la méthode n'est appelée nulle part | Documentée ; à supprimer ou réécrire si elle devient nécessaire |

## 7. Preuves

- `docs/preuves/lot3-quality-gates.txt` : démonstration d'échec puis de succès de PHPStan, de PHP-CS-Fixer et du seuil de couverture, et liste des tests exécutés.
- Logs et artefact `test-reports-<sha>` dans l'onglet **Actions** de GitHub.
