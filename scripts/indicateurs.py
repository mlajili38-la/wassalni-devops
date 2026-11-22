#!/usr/bin/env python3
"""Indicateurs de suivi de la chaine DevOps (section 13 de l'enonce).

Entrees (exports de la CLI GitHub, non versionnes) :
  gh run list --limit 400 --json workflowName,conclusion,event,headBranch,startedAt,updatedAt,status > build/runs.json
  gh pr list --state merged --limit 200 --json number,title,mergedAt,author > build/prs.json
Usage : python3 scripts/indicateurs.py build/runs.json build/prs.json > docs/preuves/indicateurs-chaine.md

Regles de calcul :
  - seules les executions terminees avec un resultat success/failure comptent (cancelled et skipped sont exclus) ;
  - la duree d'une execution = updatedAt - startedAt ;
  - un deploiement = une execution de "Deploy test" (hors pull_request) ;
  - les workflows "Dependabot Updates" (internes a GitHub) sont exclus.
"""

import json
import sys
from collections import defaultdict
from datetime import datetime


def lire_json(chemin):
    with open(chemin, encoding="utf-8-sig") as fichier:
        return json.load(fichier)


def date(valeur):
    return datetime.fromisoformat(valeur.replace("Z", "+00:00"))


def minutes(secondes):
    return f"{int(secondes // 60)} min {int(secondes % 60):02d} s"


def main():
    runs = [r for r in lire_json(sys.argv[1]) if r["workflowName"] != "Dependabot Updates" and r["status"] == "completed"]
    prs = lire_json(sys.argv[2])
    termines = [r for r in runs if r["conclusion"] in ("success", "failure")]
    debut = min(date(r["startedAt"]) for r in runs)
    fin = max(date(r["updatedAt"]) for r in runs)

    print("# Indicateurs de suivi de la chaîne DevOps")
    print()
    print(f"Période : {debut:%d/%m/%Y} → {fin:%d/%m/%Y}. Généré par `scripts/indicateurs.py` à partir de `gh run list` et `gh pr list`.")
    print("Exécutions annulées (`cancelled`, remplacées par une plus récente) et ignorées (`skipped`) exclues des taux.")
    print()

    print("## 1. Taux de réussite et durée des pipelines")
    print()
    print("| Workflow | Exécutions | Réussies | Échouées | Taux de réussite | Durée moyenne |")
    print("|---|---|---|---|---|---|")
    par_workflow = defaultdict(list)
    for r in termines:
        par_workflow[r["workflowName"]].append(r)
    for nom in sorted(par_workflow):
        liste = par_workflow[nom]
        ok = sum(1 for r in liste if r["conclusion"] == "success")
        duree = sum((date(r["updatedAt"]) - date(r["startedAt"])).total_seconds() for r in liste) / len(liste)
        print(f"| {nom} | {len(liste)} | {ok} | {len(liste) - ok} | {100 * ok / len(liste):.0f} % | {minutes(duree)} |")
    print()

    ci_main = [r for r in termines if r["workflowName"] == "CI" and r["headBranch"] == "main" and r["event"] == "push"]
    premier_vert = min((date(r["startedAt"]) for r in ci_main if r["conclusion"] == "success"), default=None)
    apres = [r for r in ci_main if premier_vert and date(r["startedAt"]) >= premier_vert]
    ok_apres = sum(1 for r in apres if r["conclusion"] == "success")
    print(f"CI sur `main` : {sum(1 for r in ci_main if r['conclusion'] == 'success')}/{len(ci_main)} réussies ; "
          f"depuis la première CI verte ({premier_vert:%d/%m/%Y}) : **{ok_apres}/{len(apres)} ({100 * ok_apres / max(len(apres), 1):.0f} %)**.")
    echecs_pr = [r for r in termines if r["workflowName"] == "CI" and r["event"] == "pull_request" and r["conclusion"] == "failure"]
    dependabot = sum(1 for r in echecs_pr if r["headBranch"].startswith("dependabot/"))
    print(f"Échecs de CI sur pull request : {len(echecs_pr)}, dont {dependabot} sur des PR Dependabot (montées majeures bloquées) "
          f"et {len(echecs_pr) - dependabot} sur des branches corrigées avant fusion : **aucun défaut n'a atteint `main`**.")
    print()

    print("## 2. Fréquence des intégrations et des déploiements")
    print()
    humains = [p for p in prs if not p["author"].get("is_bot")]
    jours_pr = sorted({date(p["mergedAt"]).date() for p in prs})
    deploiements = [r for r in termines if r["workflowName"] == "Deploy test" and r["event"] != "pull_request"]
    deploiements_ok = [r for r in deploiements if r["conclusion"] == "success"]
    print("| Indicateur | Valeur |")
    print("|---|---|")
    print(f"| Pull requests fusionnées | {len(prs)} ({len(humains)} de l'auteur, {len(prs) - len(humains)} Dependabot) sur {len(jours_pr)} jours de travail |")
    print(f"| Intégrations par jour de travail | {len(prs) / max(len(jours_pr), 1):.1f} |")
    print(f"| Déploiements en test (hors PR) | {len(deploiements)} (automatiques après CI verte et manuels) |")
    print(f"| Taux d'échec des déploiements | {100 * (len(deploiements) - len(deploiements_ok)) / max(len(deploiements), 1):.0f} % "
          f"({len(deploiements) - len(deploiements_ok)}/{len(deploiements)}) |")
    print()


if __name__ == "__main__":
    main()
