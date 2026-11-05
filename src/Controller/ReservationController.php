<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Trajet;
use App\Repository\ReservationRepository;
use App\Repository\TrajetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
class ReservationController extends AbstractController
{
    #[Route('/new/{id}', name: 'reservation_new', methods: ['POST'])]
    #[IsGranted('ROLE_PASSAGER')]
    public function new(Trajet $trajet, Request $request, EntityManagerInterface $em): Response
    {
        $nbPlaces = (int) $request->request->get('nbPlaces', 1);
        
        if ($trajet->getPlacesDisponibles() < $nbPlaces) {
            $this->addFlash('error', 'Pas assez de places disponibles !');
            return $this->redirectToRoute('trajet_search');
        }
        
        $reservation = new Reservation();
        $reservation->setPassager($this->getUser());
        $reservation->setTrajet($trajet);
        $reservation->setNbPlaces($nbPlaces);
        $reservation->setDateReservation(new \DateTime());
        $reservation->setStatut('CONFIRMEE');
        
        // Calculer le prix total
        $prixTotal = $trajet->getPrix() * $nbPlaces;
        $reservation->setPrixTotal($prixTotal);
        
        // Mettre à jour les places disponibles
        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() - $nbPlaces);
        
        $em->persist($reservation);
        $em->flush();
        
        $this->addFlash('success', 'Réservation confirmée !');
        return $this->redirectToRoute('trajet_search');
    }
    
    #[Route('/annuler/{id}', name: 'reservation_cancel')]
    #[IsGranted('ROLE_PASSAGER')]
    public function cancel(Reservation $reservation, EntityManagerInterface $em): Response
    {
        if ($reservation->getPassager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas annuler cette réservation.');
        }
        
        if ($reservation->getStatut() !== 'CONFIRMEE') {
            $this->addFlash('error', 'Cette réservation est déjà annulée.');
            return $this->redirectToRoute('mes_reservations');
        }
        
        // Rendre les places au trajet
        $trajet = $reservation->getTrajet();
        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() + $reservation->getNbPlaces());
        
        $reservation->setStatut('ANNULEE');
        $reservation->setDateAnnulation(new \DateTime());
        
        $em->flush();
        
        $this->addFlash('success', 'Réservation annulée avec succès.');
        return $this->redirectToRoute('mes_reservations');
    }
    
    #[Route('/mes-reservations', name: 'mes_reservations')]
    #[IsGranted('ROLE_PASSAGER')]
    public function mesReservations(ReservationRepository $reservationRepo): Response
    {
        $user = $this->getUser();
        $reservations = $reservationRepo->findBy(
            ['passager' => $user],
            ['dateReservation' => 'DESC']
        );
        
        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations
        ]);
    }
    
    #[Route('/new-ajax', name: 'reservation_new_ajax', methods: ['POST'])]
    #[IsGranted('ROLE_PASSAGER')]
    //Version AJAX pour réserver sans recharger la page.
    public function newAjax(Request $request, EntityManagerInterface $em, TrajetRepository $trajetRepo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $trajetId = $data['trajetId'] ?? null;
        $nbPlaces = (int) ($data['nbPlaces'] ?? 1);
        
        if (!$trajetId) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Trajet non spécifié'
            ], 400);
        }
        
        // Récupérer le trajet
        $trajet = $trajetRepo->find($trajetId);
        
        // Vérifier que le trajet existe
        if (!$trajet) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Trajet non trouvé'
            ], 404);
        }
        
        // Vérifier que le trajet est validé
        if ($trajet->getStatut() !== 'VALIDE') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ce trajet n\'est pas disponible'
            ], 400);
        }
        
        // Vérifier qu'il y a assez de places
        if ($trajet->getPlacesDisponibles() < $nbPlaces) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Pas assez de places disponibles. Il reste ' . $trajet->getPlacesDisponibles() . ' place(s).'
            ], 400);
        }
        
        // Vérifier que le passager n'a pas déjà une réservation pour ce trajet
        $existingReservation = $em->getRepository(Reservation::class)->findOneBy([
            'passager' => $this->getUser(),
            'trajet' => $trajet,
            'statut' => 'CONFIRMEE'
        ]);
        
        if ($existingReservation) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Vous avez déjà réservé ce trajet'
            ], 400);
        }
        
        // Créer la réservation
        $reservation = new Reservation();
        $reservation->setPassager($this->getUser());
        $reservation->setTrajet($trajet);
        $reservation->setNbPlaces($nbPlaces);
        $reservation->setDateReservation(new \DateTime());
        $reservation->setStatut('CONFIRMEE');
        $reservation->setPrixTotal($trajet->getPrix() * $nbPlaces);
        
        // Mettre à jour les places disponibles du trajet
        $trajet->setPlacesDisponibles($trajet->getPlacesDisponibles() - $nbPlaces);
        
        // Sauvegarder en base de données
        $em->persist($reservation);
        $em->flush();
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Réservation confirmée pour ' . $nbPlaces . ' place(s) !',
            'reservationId' => $reservation->getId(),
            'prixTotal' => $reservation->getPrixTotal()
        ]);
    }
}