<?php

// Formatage du code PHP (norme @Symfony). En CI : "vendor/bin/php-cs-fixer check" (echoue si un fichier n'est pas conforme).
// En local, pour corriger automatiquement : "vendor/bin/php-cs-fixer fix".
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__.'/src', __DIR__.'/tests'])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache')
;
