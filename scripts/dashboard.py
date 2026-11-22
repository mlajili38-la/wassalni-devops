#!/usr/bin/env python3
"""Tableau de bord de supervision (Lot 7).

Lit le fichier CSV produit par scripts/monitor-health.sh (une mesure de /health par seconde) et produit :
  - un tableau de bord HTML autonome (aucune dependance, graphiques SVG integres) ;
  - un tableau Markdown des indicateurs sur la sortie standard (resume du job GitHub Actions).

Usage : python3 scripts/dashboard.py <monitoring.csv> <dashboard.html> [metrics.env]
  metrics.env (optionnel) : lignes CLE=valeur ecrites par scripts/incident-drill.sh
  (MTTD, MTTR, DUREE_ROLLBACK, VERSION_STABLE, VERSION_DEFECTUEUSE).
Colonnes du CSV : horodatage_utc,epoch,code_http,duree_s,statut
"""

import csv
import html
import sys
from pathlib import Path


def lire_mesures(chemin):
    with open(chemin, newline="", encoding="utf-8") as fichier:
        return [
            {
                "heure": ligne["horodatage_utc"],
                "code": ligne["code_http"],
                "duree_ms": float(ligne["duree_s"] or 0) * 1000,
                "up": ligne["statut"] == "UP",
            }
            for ligne in csv.DictReader(fichier)
        ]


def lire_metriques(chemin):
    metriques = {}
    if chemin and Path(chemin).is_file():
        for ligne in Path(chemin).read_text(encoding="utf-8").splitlines():
            if "=" in ligne:
                cle, valeur = ligne.split("=", 1)
                metriques[cle.strip()] = valeur.strip()
    return metriques


def percentile(valeurs, p):
    if not valeurs:
        return 0.0
    triees = sorted(valeurs)
    return triees[min(len(triees) - 1, int(round(p / 100 * (len(triees) - 1))))]


def calculer(mesures):
    total = len(mesures)
    up = sum(1 for m in mesures if m["up"])
    durees_up = [m["duree_ms"] for m in mesures if m["up"]]
    down = [m for m in mesures if not m["up"]]
    return {
        "total": total,
        "up": up,
        "down": total - up,
        "disponibilite": 100 * up / total if total else 0.0,
        "erreurs_5xx": sum(1 for m in mesures if m["code"].startswith("5")),
        "sans_reponse": sum(1 for m in mesures if m["code"] == "000"),
        "moyenne_ms": sum(durees_up) / len(durees_up) if durees_up else 0.0,
        "p95_ms": percentile(durees_up, 95),
        "max_ms": max(durees_up) if durees_up else 0.0,
        "debut_panne": down[0]["heure"] if down else "-",
        "fin_panne": down[-1]["heure"] if down else "-",
        "etat_final": "UP" if mesures and mesures[-1]["up"] else "DOWN",
    }


def svg_etat(mesures, largeur=900, hauteur=40):
    """Frise : une barre par mesure, verte si UP, rouge si DOWN."""
    if not mesures:
        return ""
    pas = largeur / len(mesures)
    barres = "".join(
        f'<rect x="{i * pas:.2f}" y="0" width="{max(pas - 0.5, 0.5):.2f}" height="{hauteur}" '
        f'fill="{"#1a7f37" if m["up"] else "#cf222e"}"><title>{html.escape(m["heure"])} : HTTP {m["code"]}</title></rect>'
        for i, m in enumerate(mesures)
    )
    return f'<svg viewBox="0 0 {largeur} {hauteur}" width="100%" role="img" aria-label="Etat du service">{barres}</svg>'


def svg_durees(mesures, largeur=900, hauteur=220, marge=40):
    """Courbe du temps de reponse de /health (ms) ; points rouges pour les mesures en echec."""
    if not mesures:
        return ""
    maxi = max(m["duree_ms"] for m in mesures) or 1
    pas = (largeur - marge) / max(len(mesures) - 1, 1)

    def point(i, m):
        return marge + i * pas, hauteur - marge / 2 - (m["duree_ms"] / maxi) * (hauteur - marge)

    ligne = " ".join(f"{x:.1f},{y:.1f}" for x, y in (point(i, m) for i, m in enumerate(mesures)))
    pannes = "".join(
        f'<circle cx="{x:.1f}" cy="{y:.1f}" r="3" fill="#cf222e"><title>{html.escape(m["heure"])} : HTTP {m["code"]}</title></circle>'
        for i, m in enumerate(mesures)
        if not m["up"]
        for x, y in [point(i, m)]
    )
    axes = (
        f'<line x1="{marge}" y1="{hauteur - marge / 2}" x2="{largeur}" y2="{hauteur - marge / 2}" stroke="#8c959f"/>'
        f'<text x="0" y="14" font-size="12" fill="#57606a">{maxi:.0f} ms</text>'
        f'<text x="0" y="{hauteur - marge / 2}" font-size="12" fill="#57606a">0 ms</text>'
        f'<text x="{marge}" y="{hauteur - 2}" font-size="12" fill="#57606a">{html.escape(mesures[0]["heure"])}</text>'
        f'<text x="{largeur - 60}" y="{hauteur - 2}" font-size="12" fill="#57606a">{html.escape(mesures[-1]["heure"])}</text>'
    )
    return (
        f'<svg viewBox="0 0 {largeur} {hauteur}" width="100%" role="img" aria-label="Temps de reponse">{axes}'
        f'<polyline points="{ligne}" fill="none" stroke="#0969da" stroke-width="1.5"/>{pannes}</svg>'
    )


def indicateurs(k, metriques):
    lignes = [
        ("Etat final du service", k["etat_final"]),
        ("Mesures de /health (1 par seconde)", f'{k["total"]} (UP {k["up"]} / DOWN {k["down"]})'),
        ("Disponibilite sur la fenetre", f'{k["disponibilite"]:.1f} %'),
        ("Indisponibilite observee", f'{k["debut_panne"]} -> {k["fin_panne"]} UTC'),
        ("Reponses en erreur 5xx", str(k["erreurs_5xx"])),
        ("Absences de reponse (redemarrage)", str(k["sans_reponse"])),
        ("Temps de reponse /health (UP) moyen / p95 / max", f'{k["moyenne_ms"]:.1f} / {k["p95_ms"]:.1f} / {k["max_ms"]:.1f} ms'),
    ]
    libelles = {
        "MTTD": "MTTD (detection de l'incident)",
        "MTTR": "MTTR (retablissement depuis la detection)",
        "DUREE_ROLLBACK": "Duree du rollback",
        "VERSION_STABLE": "Version stable (rollback)",
        "VERSION_DEFECTUEUSE": "Version defectueuse",
    }
    for cle, libelle in libelles.items():
        if cle in metriques:
            valeur = metriques[cle]
            lignes.append((libelle, f"{valeur} s" if cle in ("MTTD", "MTTR", "DUREE_ROLLBACK") else valeur))
    return lignes


def page_html(lignes, k, mesures):
    couleur = "#1a7f37" if k["etat_final"] == "UP" else "#cf222e"
    tableau = "".join(f"<tr><th>{html.escape(a)}</th><td>{html.escape(b)}</td></tr>" for a, b in lignes)
    return f"""<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>WASSALNI - tableau de bord de supervision</title>
<style>
body {{ font-family: system-ui, sans-serif; margin: 2rem auto; max-width: 960px; color: #1f2328; }}
h1 {{ font-size: 1.5rem; }} h2 {{ font-size: 1.1rem; margin-top: 2rem; }}
.cartes {{ display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }}
.carte {{ border: 1px solid #d0d7de; border-radius: 8px; padding: 1rem; }}
.carte b {{ display: block; font-size: 1.6rem; }}
table {{ border-collapse: collapse; width: 100%; }} th, td {{ border-bottom: 1px solid #d0d7de; padding: .4rem; text-align: left; }}
.legende span {{ display: inline-block; width: 12px; height: 12px; margin: 0 .3rem 0 1rem; vertical-align: middle; }}
</style></head><body>
<h1>WASSALNI - supervision de l'environnement de test</h1>
<p>Source : sonde <code>GET /health</code> interrogee chaque seconde (<code>scripts/monitor-health.sh</code>).</p>
<div class="cartes">
  <div class="carte">Etat final<b style="color:{couleur}">{k["etat_final"]}</b></div>
  <div class="carte">Disponibilite<b>{k["disponibilite"]:.1f} %</b></div>
  <div class="carte">Erreurs 5xx<b>{k["erreurs_5xx"]}</b></div>
  <div class="carte">Reponse moyenne<b>{k["moyenne_ms"]:.1f} ms</b></div>
</div>
<h2>Etat du service dans le temps</h2>
<p class="legende"><span style="background:#1a7f37"></span>UP (HTTP 200)<span style="background:#cf222e"></span>DOWN (5xx ou pas de reponse)</p>
{svg_etat(mesures)}
<h2>Temps de reponse de /health</h2>
{svg_durees(mesures)}
<h2>Indicateurs</h2>
<table>{tableau}</table>
</body></html>
"""


def main():
    if len(sys.argv) < 3:
        sys.exit("Usage : python3 scripts/dashboard.py <monitoring.csv> <dashboard.html> [metrics.env]")
    mesures = lire_mesures(sys.argv[1])
    if not mesures:
        sys.exit(f"Aucune mesure dans {sys.argv[1]}")
    metriques = lire_metriques(sys.argv[3] if len(sys.argv) > 3 else None)
    k = calculer(mesures)
    lignes = indicateurs(k, metriques)
    Path(sys.argv[2]).write_text(page_html(lignes, k, mesures), encoding="utf-8")

    print("## Tableau de bord de supervision")
    print()
    print("| Indicateur | Valeur |")
    print("|---|---|")
    for libelle, valeur in lignes:
        print(f"| {libelle} | {valeur} |")
    print()
    print(f"Tableau de bord HTML (graphiques) : artefact du run, fichier `{Path(sys.argv[2]).name}`.")


if __name__ == "__main__":
    main()
