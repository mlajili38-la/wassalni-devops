<?php

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Entity\Trajet;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReservationServiceTest extends TestCase
{
    private Trajet $trajet;
    private User $passager;

    // Fixture : objets neufs avant CHAQUE test (isolation des tests)
    protected function setUp(): void
    {
        $this->trajet = new Trajet();
        $this->trajet->setPrix(10.0);
        $this->trajet->setPlacesDisponibles(3);

        $this->passager = new User();
    }

    // STUB : renvoie une réponse préprogrammée, sans vérifier comment il est appelé
    private function creerServiceAvecStub(?Reservation $reservationExistante = null): ReservationService
    {
        $repository = $this->createStub(ReservationRepository::class);
        $repository->method('findOneBy')->willReturn($reservationExistante);

        return new ReservationService($repository);
    }

    private function creerReservationConfirmee(int $nbPlaces): Reservation
    {
        $reservation = new Reservation();
        $reservation->setTrajet($this->trajet);
        $reservation->setNbPlaces($nbPlaces);
        $reservation->setStatut('CONFIRMEE');

        return $reservation;
    }

    #[Test]
    public function reserver_placesSuffisantes_creeReservationConfirmee(): void
    {
        // Arrange
        $service = $this->creerServiceAvecStub();

        // Act
        $reservation = $service->reserver($this->trajet, $this->passager, 2);

        // Assert
        $this->assertSame('CONFIRMEE', $reservation->getStatut());
    }

    #[Test]
    public function reserver_placesSuffisantes_diminueLesPlacesDuTrajet(): void
    {
        // Arrange
        $service = $this->creerServiceAvecStub();

        // Act
        $service->reserver($this->trajet, $this->passager, 2);

        // Assert
        $this->assertSame(1, $this->trajet->getPlacesDisponibles());
    }

    #[Test]
    public function reserver_pasAssezDePlaces_leveException(): void
    {
        // Arrange
        $service = $this->creerServiceAvecStub();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Pas assez de places disponibles.');

        // Act + Assert (l'exception attendue est la vérification)
        $service->reserver($this->trajet, $this->passager, 5);
    }

    #[Test]
    public function reserver_dejaReserveSurCeTrajet_leveException(): void
    {
        // Arrange : MOCK du repository, qui vérifie aussi l'interaction
        $reservationExistante = $this->creerReservationConfirmee(1);
        $repository = $this->createMock(ReservationRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($reservationExistante);
        $service = new ReservationService($repository);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Vous avez déjà réservé ce trajet.');

        // Act + Assert
        $service->reserver($this->trajet, $this->passager, 1);
    }

    #[Test]
    public function annuler_reservationConfirmee_passeAAnnulee(): void
    {
        // Arrange
        $service = $this->creerServiceAvecStub();
        $reservation = $this->creerReservationConfirmee(2);

        // Act
        $service->annuler($reservation);

        // Assert
        $this->assertSame('ANNULEE', $reservation->getStatut());
    }

    #[Test]
    public function annuler_reservationConfirmee_rendLesPlacesAuTrajet(): void
    {
        // Arrange
        $service = $this->creerServiceAvecStub();
        $reservation = $this->creerReservationConfirmee(2);

        // Act
        $service->annuler($reservation);

        // Assert : 3 places au départ + 2 rendues = 5
        $this->assertSame(5, $this->trajet->getPlacesDisponibles());
    }

    #[Test]
    public function annuler_reservationDejaAnnulee_leveException(): void
    {
        // Arrange
        $service = $this->creerServiceAvecStub();
        $reservation = $this->creerReservationConfirmee(2);
        $reservation->setStatut('ANNULEE');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cette réservation est déjà annulée.');

        // Act + Assert
        $service->annuler($reservation);
    }
}