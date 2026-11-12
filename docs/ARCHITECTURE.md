# Application et architecture

## 1. L'application

WASSALNI met en relation des **conducteurs** qui publient des trajets et des **passagers** qui les réservent.

| Rôle | Fonctionnalités principales |
|---|---|
| Visiteur | accueil, recherche de trajets, inscription en 4 étapes (`/inscription/etape/{n}`), connexion |
| Passager (`ROLE_PASSAGER`) | réserver un trajet, annuler, consulter ses réservations, laisser un avis, déposer une réclamation |
| Conducteur (`ROLE_CONDUCTEUR`) | gérer ses véhicules, publier ses trajets, traiter les demandes, consulter ses avis |
| Administrateur (`ROLE_ADMIN`) | tableau de bord, activer/bloquer des utilisateurs, valider des trajets, traiter les réclamations |

Fonctionnalités métier testées automatiquement : **réservation** (places, prix, double réservation, annulation)
et **gestion des utilisateurs** (enregistrement, unicité de l'email, validation), plus la sonde `/health`.

Modèle de données (Doctrine, 4 migrations MySQL) : `User`, `Vehicule`, `Trajet`, `Reservation`, `Avis`, `Reclamation`
(+ `messenger_messages`, `doctrine_migration_versions`).

```
User 1─* Vehicule 1─* Trajet *─1 User (conducteur)
User 1─* Reservation *─1 Trajet          User 1─* Avis *─1 Trajet          User 1─* Reclamation
```

## 2. Architecture technique

| Couche | Technologie |
|---|---|
| Application | Symfony 7.4 (contrôleurs, Twig, formulaires, sécurité), PHP 8.3 |
| Données | Doctrine ORM + Migrations, MySQL 8.0 |
| Serveur web | Apache 2.4 (image `php:8.3-apache`) |
| Conteneurs | `Dockerfile` (image applicative), `compose.yaml` (`app` + `database`, réseau Compose, volume `database_data`) |
| Démarrage | `docker/entrypoint.sh` : attente de MySQL → migrations Doctrine → Apache |
| Sondes | `GET /health` (Symfony + MySQL), healthchecks Docker de `app` et `database` |
| Logs | Apache (accès/erreurs) et Monolog JSON sur stdout/stderr, collectés par Docker |

```
                    ┌──────────────────────── stack Docker Compose ────────────────────────┐
 navigateur ─HTTP─► │ app : Apache + PHP 8.3 + Symfony (port 8080→80)  ──TCP 3306──►  database : MySQL 8.0 │
                    │   healthcheck : curl /health                      healthcheck : mysqladmin ping │
                    │   logs : stdout/stderr                            volume : database_data        │
                    └───────────────────────────────────────────────────────────────────────┘
 Configuration et secrets injectés au démarrage (.env.docker en local, secrets GitHub en CI), jamais dans l'image.
```

## 3. Chaîne DevOps (outils)

| Besoin | Outil retenu |
|---|---|
| Code, revues, PR | Git, GitHub (GitHub Flow : branches courtes + PR + CI verte ; protection technique de `main` indisponible sur un dépôt privé gratuit, voir le bilan) |
| CI/CD | GitHub Actions (3 workflows) |
| Dépendances, build | Composer (`composer.lock`), Docker |
| Qualité | PHP-CS-Fixer, PHPStan niveau 5, PHPUnit 12 + PCOV |
| Sécurité | Gitleaks, Trivy (dépendances + image), `composer audit`, Dependabot |
| Registre | GitHub Container Registry (tags = SHA du commit + `latest`) |
| Déploiement | Docker Compose dans GitHub Actions (environnement de test éphémère) |
| Supervision | `/health`, healthchecks Docker, logs collectés, sonde `monitor-health.sh`, résumés de jobs et artefacts |
