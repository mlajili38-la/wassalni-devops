<?php

namespace App\Tests\Functional;

use App\Entity\Trajet;

/**
 * Recherche publique des trajets et publication d'un trajet par un conducteur connecte.
 */
class TrajetControllerTest extends FunctionalTestCase
{
    public function testRechercheNAfficheQueLesTrajetsValidesDeLaVilleDemandee(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.recherche@test.tn', ['ROLE_CONDUCTEUR']);
        $this->creerTrajet($conducteur)->setVilleDepart('Ksour Essef');
        $this->creerTrajet($conducteur)->setVilleDepart('Bekalta');
        $this->creerTrajet($conducteur, statut: 'EN_ATTENTE')->setVilleDepart('Ksour Essef')->setDestination('Chebba');
        $this->sauvegarder();

        $this->client->request('GET', '/trajet/recherche', ['villeDepart' => 'Ksour Essef']);

        self::assertResponseIsSuccessful();
        $contenu = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Ksour Essef', $contenu);
        self::assertStringNotContainsString('Bekalta', $contenu, 'Filtre ville de depart non applique');
        self::assertStringNotContainsString('Chebba', $contenu, 'Un trajet non valide est affiche');
    }

    public function testConducteurSansVehiculeEstInviteAEnAjouterUn(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.sansvehicule@test.tn', ['ROLE_CONDUCTEUR']);
        $this->sauvegarder();
        $this->connecter($conducteur);

        $this->client->request('GET', '/trajet/nouveau');

        self::assertResponseRedirects('/vehicule/nouveau');
    }

    public function testConducteurPublieUnTrajet(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.publie@test.tn', ['ROLE_CONDUCTEUR']);
        $vehicule = $this->creerVehicule($conducteur, places: 4);
        $this->sauvegarder();
        $this->connecter($conducteur);

        $this->client->request('POST', '/trajet/nouveau', [
            'villeDepart' => 'Sayada',
            'destination' => 'Tunis',
            'dateDepart' => (new \DateTime('+3 days'))->format('d/m/Y'),
            'dateArrivee' => (new \DateTime('+3 days'))->format('d/m/Y'),
            'prix' => '15',
            'vehicule' => (string) $vehicule->getId(),
            'description' => 'Depart devant la gare',
        ]);

        self::assertResponseRedirects('/conducteur/dashboard');
        $trajet = $this->em->getRepository(Trajet::class)->findOneBy(['villeDepart' => 'Sayada']);
        self::assertNotNull($trajet);
        self::assertSame(4, $trajet->getPlacesDisponibles());
        self::assertSame('VALIDE', $trajet->getStatut());
    }

    public function testPublicationRefuseeSiChampsObligatoiresManquants(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.incomplet@test.tn', ['ROLE_CONDUCTEUR']);
        $this->creerVehicule($conducteur);
        $this->sauvegarder();
        $this->connecter($conducteur);

        $this->client->request('POST', '/trajet/nouveau', ['villeDepart' => 'Ksibet', 'prix' => '-5']);

        self::assertResponseRedirects('/trajet/nouveau');
        self::assertNull($this->em->getRepository(Trajet::class)->findOneBy(['villeDepart' => 'Ksibet']));
    }
}
