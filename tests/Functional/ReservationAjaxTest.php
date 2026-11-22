<?php

namespace App\Tests\Functional;

use App\Entity\Trajet;

/**
 * API JSON de reservation (POST /reservation/new-ajax) : codes HTTP et messages pour chaque regle metier.
 */
class ReservationAjaxTest extends FunctionalTestCase
{
    /**
     * @param array<string, mixed> $donnees
     *
     * @return array<string, mixed>
     */
    private function reserverEnAjax(array $donnees): array
    {
        $this->client->request(
            'POST',
            '/reservation/new-ajax',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($donnees)
        );

        return (array) json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    public function testTrajetNonSpecifieRetourne400(): void
    {
        $passager = $this->creerUtilisateur('ajax.vide@test.tn');
        $this->sauvegarder();
        $this->connecter($passager);

        $reponse = $this->reserverEnAjax(['nbPlaces' => 1]);

        self::assertResponseStatusCodeSame(400);
        self::assertFalse($reponse['success']);
    }

    public function testTrajetInexistantRetourne404(): void
    {
        $passager = $this->creerUtilisateur('ajax.inconnu@test.tn');
        $this->sauvegarder();
        $this->connecter($passager);

        $reponse = $this->reserverEnAjax(['trajetId' => 999999, 'nbPlaces' => 1]);

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($reponse['success']);
    }

    public function testTrajetNonValideRetourne400(): void
    {
        $conducteur = $this->creerUtilisateur('ajax.conducteur.attente@test.tn', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('ajax.attente@test.tn');
        $trajet = $this->creerTrajet($conducteur, statut: 'EN_ATTENTE');
        $this->sauvegarder();
        $this->connecter($passager);

        $this->reserverEnAjax(['trajetId' => $trajet->getId(), 'nbPlaces' => 1]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testReservationAjaxReussieDecrementeLesPlaces(): void
    {
        $conducteur = $this->creerUtilisateur('ajax.conducteur.ok@test.tn', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('ajax.ok@test.tn');
        $trajet = $this->creerTrajet($conducteur, places: 3, prix: 8.0);
        $this->sauvegarder();
        $this->connecter($passager);

        $reponse = $this->reserverEnAjax(['trajetId' => $trajet->getId(), 'nbPlaces' => 2]);

        self::assertResponseIsSuccessful();
        self::assertTrue($reponse['success']);
        self::assertEquals(16, $reponse['prixTotal']);
        self::assertSame(1, $this->relire(Trajet::class, $trajet->getId())?->getPlacesDisponibles());
    }

    public function testDoubleReservationAjaxRefusee(): void
    {
        $conducteur = $this->creerUtilisateur('ajax.conducteur.double@test.tn', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('ajax.double@test.tn');
        $trajet = $this->creerTrajet($conducteur, places: 3);
        $this->sauvegarder();
        $this->connecter($passager);
        $this->reserverEnAjax(['trajetId' => $trajet->getId(), 'nbPlaces' => 1]);

        $reponse = $this->reserverEnAjax(['trajetId' => $trajet->getId(), 'nbPlaces' => 1]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('Vous avez déjà réservé ce trajet', $reponse['message']);
        self::assertSame(2, $this->relire(Trajet::class, $trajet->getId())?->getPlacesDisponibles());
    }
}
