<?php

namespace App\Tests\Functional;

use App\Entity\Reclamation;
use App\Entity\Trajet;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Espace d'administration : consultation et actions de moderation (trajets, utilisateurs, reclamations).
 */
class AdminControllerTest extends FunctionalTestCase
{
    private function creerAdmin(): User
    {
        return $this->creerUtilisateur('admin.fonctionnel@test.tn', ['ROLE_ADMIN']);
    }

    #[DataProvider('pagesAdmin')]
    public function testAdminConsulteLesPagesDAdministration(string $url): void
    {
        $admin = $this->creerAdmin();
        $conducteur = $this->creerUtilisateur('conducteur.admin@test.tn', ['ROLE_CONDUCTEUR']);
        $this->creerTrajet($conducteur);
        $this->creerReclamation($conducteur);
        $this->sauvegarder();
        $this->connecter($admin);

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    public static function pagesAdmin(): iterable
    {
        yield 'tableau de bord' => ['/admin/dashboard'];
        yield 'utilisateurs' => ['/admin/utilisateurs'];
        yield 'trajets' => ['/admin/trajets'];
        yield 'reclamations' => ['/admin/reclamations'];
    }

    public function testAdminValideUnTrajetEnAttente(): void
    {
        $admin = $this->creerAdmin();
        $conducteur = $this->creerUtilisateur('conducteur.valider@test.tn', ['ROLE_CONDUCTEUR']);
        $trajet = $this->creerTrajet($conducteur, statut: 'EN_ATTENTE');
        $this->sauvegarder();
        $this->connecter($admin);

        $this->client->request('GET', '/admin/trajet/valider/'.$trajet->getId());

        self::assertResponseRedirects('/admin/trajets');
        self::assertSame('VALIDE', $this->relire(Trajet::class, $trajet->getId())?->getStatut());
    }

    public function testAdminBloquePuisReactiveUnUtilisateur(): void
    {
        $admin = $this->creerAdmin();
        $passager = $this->creerUtilisateur('passager.bloque@test.tn');
        $this->sauvegarder();
        $this->connecter($admin);

        $this->client->request('GET', '/admin/utilisateur/bloquer/'.$passager->getId());
        self::assertResponseRedirects('/admin/utilisateurs');
        $bloque = $this->relire(User::class, $passager->getId());
        self::assertNotNull($bloque);
        self::assertSame('BLOQUE', $bloque->getStatut());

        $this->client->request('GET', '/admin/utilisateur/activer/'.$passager->getId());
        $reactive = $this->relire(User::class, $passager->getId());
        self::assertNotNull($reactive);
        self::assertSame('ACTIF', $reactive->getStatut());
    }

    public function testAdminTraiteUneReclamationAvecUneReponse(): void
    {
        $admin = $this->creerAdmin();
        $passager = $this->creerUtilisateur('passager.reclame@test.tn');
        $reclamation = $this->creerReclamation($passager);
        $this->sauvegarder();
        $this->connecter($admin);

        $this->client->request('POST', '/admin/reclamation/traiter/'.$reclamation->getId(), [
            'reponse' => 'Le conducteur a ete averti.',
        ]);

        self::assertResponseRedirects('/admin/reclamations');
        $traitee = $this->relire(Reclamation::class, $reclamation->getId());
        self::assertNotNull($traitee);
        self::assertSame('TRAITEE', $traitee->getStatut());
        self::assertSame('Le conducteur a ete averti.', $traitee->getReponse());
        self::assertNotNull($traitee->getDateTraitement());
    }

    public function testReclamationSansReponseResteEnAttente(): void
    {
        $admin = $this->creerAdmin();
        $passager = $this->creerUtilisateur('passager.sansreponse@test.tn');
        $reclamation = $this->creerReclamation($passager);
        $this->sauvegarder();
        $this->connecter($admin);

        $this->client->request('POST', '/admin/reclamation/traiter/'.$reclamation->getId(), ['reponse' => '']);

        self::assertResponseRedirects('/admin/reclamations');
        self::assertSame('EN_ATTENTE', $this->relire(Reclamation::class, $reclamation->getId())?->getStatut());
    }
}
