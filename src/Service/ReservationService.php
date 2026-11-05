<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Trajet;
use App\Entity\User;
use App\Repository\ReservationRepository;

class ReservationService
{
    public function __construct(private ReservationRepository $reservationRepository)
    {
    }

    /**
     * Prépare une réservation confirmée et décrémente les places du trajet.
     * La sauvegarde en base reste à la charge de l'appelant (persist + flush).
     */
    public function reserver(Trajet $trajet, User $passager, int $nbPlaces): Reservation
    {
        if ($trajet->getPlacesDisponibles() < $nbPlaces) {
            throw new \DomainException('Pas assez de places disponibles.');
        }

        $reservationExistante = $this->reservationRepository->findOneBy([
            'passager' => $passager,
            'trajet' => $trajet,
            'statut' => 'CONFIRMEE',
        ]);

        if ($reservationExistante !== null) {
            throw new \DomainException('Vous avez déjà réservé ce trajet.');
        }

        $reservation = new Reservation();
        $reservation->setPassager($passager);
        $reservation->setTrajet($trajet);
        $reservation->setNbPlaces($nbPlaces);
        $reservation->setDateReservation(new \DateTime());
        $reservation->setStatut('CONFIRMEE');
        $reservation->setPrixTotal($trajet->getPrix() * $nbPlaces);

        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() - $nbPlaces);

        return $reservation;
    }

    /**
     * Annule une réservation confirmée et rend les places au trajet.
     */
    public function annuler(Reservation $reservation): void
    {
        if ($reservation->getStatut() !== 'CONFIRMEE') {
            throw new \DomainException('Cette réservation est déjà annulée.');
        }

        $trajet = $reservation->getTrajet();
        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getNbPlaces());

        $reservation->setStatut('ANNULEE');
    }
}