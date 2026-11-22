# Indicateurs de suivi de la chaîne DevOps

Période : 05/11/2026 → 22/11/2026. Généré par `scripts/indicateurs.py` à partir de `gh run list` et `gh pr list`.
Exécutions annulées (`cancelled`, remplacées par une plus récente) et ignorées (`skipped`) exclues des taux.

## 1. Taux de réussite et durée des pipelines

| Workflow | Exécutions | Réussies | Échouées | Taux de réussite | Durée moyenne |
|---|---|---|---|---|---|
| CI | 76 | 64 | 12 | 84 % | 2 min 53 s |
| Deploy test | 29 | 29 | 0 | 100 % | 1 min 00 s |
| Incident drill and rollback | 9 | 7 | 2 | 78 % | 1 min 21 s |

CI sur `main` : 22/27 réussies ; depuis la première CI verte (07/11/2026) : **22/22 (100 %)**.
Échecs de CI sur pull request : 7, dont 5 sur des PR Dependabot (montées majeures bloquées) et 2 sur des branches corrigées avant fusion : **aucun défaut n'a atteint `main`**.

## 2. Fréquence des intégrations et des déploiements

| Indicateur | Valeur |
|---|---|
| Pull requests fusionnées | 25 (20 de l'auteur, 5 Dependabot) sur 3 jours de travail |
| Intégrations par jour de travail | 8.3 |
| Déploiements en test (hors PR) | 17 (automatiques après CI verte et manuels) |
| Taux d'échec des déploiements | 0 % (0/17) |

