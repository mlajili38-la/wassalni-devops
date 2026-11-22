<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Securite : chaque espace (passager, conducteur, administration) n'est accessible qu'au bon role.
 */
class ControleAccesTest extends FunctionalTestCase
{
    #[DataProvider('pagesProtegees')]
    public function testVisiteurAnonymeEstRedirigeVersLaConnexion(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public static function pagesProtegees(): iterable
    {
        yield 'administration' => ['/admin/dashboard'];
        yield 'mes reservations' => ['/reservation/mes-reservations'];
        yield 'mes vehicules' => ['/vehicule/mes-vehicules'];
        yield 'mes reclamations' => ['/reclamation/mes-reclamations'];
    }

    #[DataProvider('pagesInterditesAuPassager')]
    public function testPassagerNAccedePasAuxEspacesConducteurEtAdmin(string $url): void
    {
        $passager = $this->creerUtilisateur('passager.acces@test.tn');
        $this->sauvegarder();
        $this->connecter($passager);

        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(403);
    }

    public static function pagesInterditesAuPassager(): iterable
    {
        yield 'tableau de bord admin' => ['/admin/dashboard'];
        yield 'liste des utilisateurs' => ['/admin/utilisateurs'];
        yield 'mes vehicules (conducteur)' => ['/vehicule/mes-vehicules'];
        yield 'publier un trajet (conducteur)' => ['/trajet/nouveau'];
    }

    public function testConducteurNAccedePasALAdministration(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.acces@test.tn', ['ROLE_CONDUCTEUR']);
        $this->sauvegarder();
        $this->connecter($conducteur);

        $this->client->request('GET', '/admin/trajets');

        self::assertResponseStatusCodeSame(403);
    }
}
