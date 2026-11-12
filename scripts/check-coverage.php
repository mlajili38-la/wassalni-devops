<?php

// Quality gate de couverture : echoue (code 1) si le taux de lignes couvertes
// du rapport Clover est inferieur au seuil.
// Usage : php scripts/check-coverage.php build/reports/clover.xml 10
[$script, $fichier, $seuil] = $argv + [null, 'build/reports/clover.xml', '10'];

if (!is_file($fichier)) {
    fwrite(STDERR, "Rapport de couverture introuvable : $fichier\n");
    exit(1);
}

$metrics = simplexml_load_file($fichier)->project->metrics;
$total = (int) $metrics['statements'];
$couvertes = (int) $metrics['coveredstatements'];
$taux = $total > 0 ? round(100 * $couvertes / $total, 2) : 0.0;

printf("Couverture des lignes : %.2f %% (%d/%d), seuil minimal : %s %%\n", $taux, $couvertes, $total, $seuil);

if ($taux < (float) $seuil) {
    fwrite(STDERR, "ECHEC : couverture inferieure au seuil.\n");
    exit(1);
}

echo "OK : seuil de couverture respecte.\n";
