<?php

namespace App\Tests\Doubles;

use Doctrine\DBAL\Exception as DBALException;

/**
 * Exception DBAL de test : simule une base de donnees injoignable.
 */
final class BaseIndisponibleException extends \RuntimeException implements DBALException
{
    public function __construct()
    {
        parent::__construct('Base de donnees injoignable (exception de test).');
    }
}
