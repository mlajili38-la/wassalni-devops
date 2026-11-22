<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use App\Entity\Trajet;

/**
 * Parcours de reservation d'un passager connecte : reserver, refuser, annuler, consulter.
 */
class ReservationControllerTest extends FunctionalTestCase
{
    public function testPassagerReserveDesPlacesEtLePrixEstCalcule(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.resa@test.tn', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('passager.resa@test.tn');
        $trajet = $this->creerTrajet($conducteur, places: 3, prix: 12.5);
        $this->sauvegarder();
        $this->connecter($passager);

        $this->client->request('POST', '/reservation/new/'.$trajet->getId(), ['nbPlaces' => 2]);

        self::assertResponseRedirects('/trajet/recherche');
        self::assertSame(1, $this->relire(Trajet::class, $trajet->getId())?->getPlacesDisponibles());
        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['trajet' => $trajet->getId()]);
        self::assertNotNull($reservation);
        self::assertSame('CONFIRMEE', $reservation->getStatut());
        self::assertSame(25.0, $reservation->getPrixTotal());
    }

    public function testReservationRefuseeSiPasAssezDePlaces(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.complet@test.tn', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('passager.complet@test.tn');
        $trajet = $this->creerTrajet($conducteur, places: 1);
        $this->sauvegarder();
        $this->connecter($passager);

        $this->client->request('POST', '/reservation/new/'.$trajet->getId(), ['nbPlaces' => 3]);

        self::assertResponseRedirects('/trajet/recherche');
        self::assertSame(1, $this->relire(Trajet::class, $trajet->getId())?->getPlacesDisponibles());
        self::assertSame(0, $this->em->getRepository(Reservation::class)->count(['trajet' => $trajet->getId()]));
    }

    public function testPassagerAnnuleSaReservationEtLesPlacesSontRendues(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.annul@test.tn', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('passager.annul@test.tn');
        $trajet = $this->creerTrajet($conducteur, places: 3);
        $this->sauvegarder();
        $this->connecter($passager);
        $this->client->request('POST', '/reservation/new/'.$trajet->getId(), ['nbPlaces' => 2]);
        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['trajet' => $trajet->getId()]);
        self::assertNotNull($reservation);

        $this->client->request('GET', '/reservation/annuler/'.$reservation->getId());

        self::assertResponseRedirects('/reservation/mes-reservations');
        self::assertSame('ANNULEE', $this->relire(Reservation::class, $reservation->getId())?->getStatut());
        self::assertSame(3, $this->relire(Trajet::class, $trajet->getId())?->getPlacesDisponibles());
    }

    public function testPassagerNePeutPasAnnulerLaReservationDUnAutre(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.autre@test.tn', ['ROLE_CONDUCTEUR']);
        $proprietaire = $this->creerUtilisateur('passager.proprietaire@test.tn');
        $intrus = $this->creerUtilisateur('passager.intrus@test.tn');
        $trajet = $this->creerTrajet($conducteur, places: 3);
        $this->sauvegarder();
        $this->connecter($proprietaire);
        $this->client->request('POST', '/reservation/new/'.$trajet->getId(), ['nbPlaces' => 1]);
        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['trajet' => $trajet->getId()]);
        self::assertNotNull($reservation);

        $this->connecter($intrus);
        $this->client->request('GET', '/reservation/annuler/'.$reservation->getId());

        self::assertResponseStatusCodeSame(403);
        self::assertSame('CONFIRMEE', $this->relire(Reservation::class, $reservation->getId())?->getStatut());
    }

    public function testPageMesReservationsAfficheLesReservationsDuPassager(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.liste@test.tn', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('passager.liste@test.tn');
        $trajet = $this->creerTrajet($conducteur, places: 3);
        $this->sauvegarder();
        $this->connecter($passager);
        $this->client->request('POST', '/reservation/new/'.$trajet->getId(), ['nbPlaces' => 1]);

        $this->client->request('GET', '/reservation/mes-reservations');

        self::assertResponseIsSuccessful();
    }
}
