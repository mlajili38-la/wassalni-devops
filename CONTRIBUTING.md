# Guide de contribution — WASSALNI

Ce guide décrit comment proposer une modification du code, de la documentation ou de la chaîne DevOps.
Toute contribution passe par une branche dédiée, une Pull Request (PR) et un pipeline CI vert.

## 1. Stratégie de branches

Le projet suit un **GitHub Flow** : une seule branche longue (`main`) et des branches courtes par sujet.

| Branche | Rôle | Durée de vie | Qui peut y pousser |
|---|---|---|---|
| `main` | Version stable, toujours déployable. Chaque commit de `main` produit une image publiée dans GHCR. | Permanente | Personne directement : uniquement par PR fusionnée |
| `feat/<sujet>` | Nouvelle fonctionnalité | Courte (quelques jours) | Auteur de la branche |
| `fix/<sujet>` | Correction de bug | Courte | Auteur de la branche |
| `docs/<sujet>` | Documentation uniquement | Courte | Auteur de la branche |
| `ci/<sujet>` | Pipeline, Docker, scripts DevOps | Courte | Auteur de la branche |
| `test/<sujet>` | Ajout ou correction de tests | Courte | Auteur de la branche |
| `chore/<sujet>` | Maintenance (dépendances, configuration) | Courte | Auteur de la branche |

Règles :

- `<sujet>` est court, en minuscules, avec des tirets : `fix/harmoniser-mysql`, `docs/contribution`.
- **Une branche = un sujet = une PR.** On ne mélange pas une correction et une nouvelle fonctionnalité.
- Une branche part toujours de `main` à jour et y revient par PR.
- **Interdit sur `main`** : push direct, force push, réécriture d'historique (`reset --hard`, `rebase`).
- Une branche fusionnée peut être supprimée sur GitHub (bouton « Delete branch » après la fusion).

Choix justifié : un `develop` séparé (Git Flow) n'apporte rien à une petite équipe qui livre en continu ;
`main` protégé + PR + CI suffisent à garantir que la version publiée est toujours testée.

## 2. Cycle de travail

```powershell
# 1. Partir de main à jour
git switch main
git pull --ff-only

# 2. Créer la branche du sujet
git switch -c feat/mon-sujet

# 3. Travailler, puis vérifier en local (PHP 8.3)
composer install
php bin/console lint:container
vendor/bin/phpunit

# 4. Commiter (voir convention ci-dessous) et pousser
git add <fichiers>
git commit -m "feat(trajet): filtrer les trajets par ville de départ"
git push -u origin feat/mon-sujet
```

5. Ouvrir une PR vers `main` sur GitHub et remplir le modèle proposé.
6. Attendre que la CI soit **verte** (jobs `quality`, `security` et `container`).
7. Faire relire la PR (revue), corriger si besoin par de nouveaux commits sur la même branche.
8. Fusionner avec **« Create a merge commit »**, puis supprimer la branche.

## 3. Convention des messages de commit

Format [Conventional Commits](https://www.conventionalcommits.org/fr/) :

```
<type>(<portée optionnelle>): <description à l'impératif, en minuscules>

<corps optionnel : pourquoi la modification>
```

| Type | Usage |
|---|---|
| `feat` | Nouvelle fonctionnalité |
| `fix` | Correction de bug |
| `docs` | Documentation |
| `test` | Tests |
| `ci` | Pipeline GitHub Actions |
| `build` | Docker, Composer, dépendances |
| `refactor` | Restructuration sans changement de comportement |
| `chore` | Maintenance diverse |

Exemples tirés du dépôt :

- `fix(db): harmoniser Docker et la CI sur MySQL 8.0`
- `fix(docker): copier .env dans l'image (sans secret) pour le build Symfony`

## 4. Règles de revue et de fusion (Definition of Done)

Une PR n'est fusionnée que si :

- [ ] la CI est verte (tests, contrôles qualité, audit des dépendances, build et déploiement de test) ;
- [ ] la description explique **quoi**, **pourquoi** et **comment ça a été vérifié** ;
- [ ] aucun secret n'apparaît dans le code, les logs, les captures ou les rapports ;
- [ ] la documentation (`docs/`) est mise à jour si le comportement ou la chaîne change ;
- [ ] la PR a été relue (revue de code ou auto-revue argumentée pour un travail individuel).

Une CI rouge ne se contourne pas : on lit les logs du job en échec, on corrige, on repousse.

## 5. Secrets et configuration

- Ne jamais commiter `.env.local`, `.env.docker` ni aucun mot de passe, jeton ou clé : ils sont ignorés par Git.
- Le fichier `.env` versionné ne contient que des valeurs neutres.
- Les valeurs nécessaires au pipeline sont stockées dans **Settings → Secrets and variables → Actions**.
- Si un secret est commité par erreur : le **révoquer immédiatement** chez le fournisseur, puis le retirer du code.
  Le supprimer du dépôt ne suffit pas, il reste dans l'historique.

## 6. Structure du dépôt

| Dossier / fichier | Contenu |
|---|---|
| `src/`, `templates/`, `config/`, `migrations/` | Application Symfony |
| `tests/` | Tests PHPUnit |
| `.github/workflows/ci.yml` | Pipeline CI/CD |
| `Dockerfile`, `compose.yaml`, `docker/` | Conteneurisation |
| `scripts/` | Scripts d'exploitation (rollback…) |
| `docs/` | Documentation DevOps et preuves d'exécution |
