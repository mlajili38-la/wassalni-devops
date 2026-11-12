<?php

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests HTTP (API / pages) : la vraie application Symfony repond avec la base MySQL de test.
 */
class PagesHttpTest extends WebTestCase
{
    public function testHealthRetourneOkAvecLaVraieBase(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', (string) $client->getResponse()->getContent());
    }

    #[DataProvider('pagesPubliques')]
    public function testPagePubliqueRepond200(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(200);
    }

    public static function pagesPubliques(): iterable
    {
        yield 'accueil' => ['/'];
        yield 'connexion' => ['/connexion'];
        yield 'inscription (ancienne route corrigee)' => ['/inscription'];
        yield 'inscription etape 1' => ['/inscription/etape/1'];
    }

    public function testPageProtegeeRedirigeVersConnexion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reservation/mes-reservations');

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', (string) $client->getResponse()->headers->get('Location'));
    }
}
