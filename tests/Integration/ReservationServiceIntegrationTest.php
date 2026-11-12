<?php

namespace App\Tests\Integration;

use App\Entity\Reservation;
use App\Service\ReservationService;

/**
 * Fonctionnalite metier n°1 : reservation d'un trajet, avec la vraie base MySQL.
 */
class ReservationServiceIntegrationTest extends DatabaseTestCase
{
    public function testReservationEstEnregistreeEtPlacesDecrementees(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.reservation@test.local', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('passager.reservation@test.local');
        $trajet = $this->creerTrajet($conducteur, places: 3, prix: 12.5);
        $this->em->flush();

        $service = new ReservationService($this->em->getRepository(Reservation::class));
        $reservation = $service->reserver($trajet, $passager, 2);
        $this->em->persist($reservation);
        $this->em->flush();
        $this->em->clear();

        $enBase = $this->em->getRepository(Reservation::class)->find($reservation->getId());
        self::assertNotNull($enBase);
        self::assertSame('CONFIRMEE', $enBase->getStatut());
        self::assertSame(25.0, $enBase->getPrixTotal());
        self::assertSame(1, $enBase->getTrajet()->getPlacesDisponibles());
    }

    public function testDoubleReservationRefuseeGraceALaRequeteEnBase(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.double@test.local', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('passager.double@test.local');
        $trajet = $this->creerTrajet($conducteur, places: 4);
        $this->em->flush();

        $service = new ReservationService($this->em->getRepository(Reservation::class));
        $this->em->persist($service->reserver($trajet, $passager, 1));
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Vous avez déjà réservé ce trajet.');
        $service->reserver($trajet, $passager, 1);
    }

    public function testAnnulationRendLesPlaces(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.annulation@test.local', ['ROLE_CONDUCTEUR']);
        $passager = $this->creerUtilisateur('passager.annulation@test.local');
        $trajet = $this->creerTrajet($conducteur, places: 3);
        $this->em->flush();

        $service = new ReservationService($this->em->getRepository(Reservation::class));
        $reservation = $service->reserver($trajet, $passager, 2);
        $this->em->persist($reservation);
        $this->em->flush();

        $service->annuler($reservation);
        $this->em->flush();
        $this->em->refresh($trajet);

        self::assertSame('ANNULEE', $reservation->getStatut());
        self::assertSame(3, $trajet->getPlacesDisponibles());
    }
}
