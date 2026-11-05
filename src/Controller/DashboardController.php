<?php

namespace App\Controller;

use App\Repository\TrajetRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    // ❌ SUPPRIME cette route car elle est déjà dans ConducteurController
    // #[Route('/conducteur/dashboard', name: 'conducteur_dashboard')]
    // #[IsGranted('ROLE_CONDUCTEUR')]
    // public function conducteurDashboard(TrajetRepository $trajetRepo): Response
    // {
    //     ...
    // }
    
    #[Route('/passager/dashboard', name: 'passager_dashboard')]
    #[IsGranted('ROLE_PASSAGER')]
    public function passagerDashboard(ReservationRepository $reservationRepo): Response
    {
        $user = $this->getUser();
        
        $mesReservations = $reservationRepo->findBy(
            ['passager' => $user],
            ['dateReservation' => 'DESC']
        );
        
        return $this->render('dashboard/passager.html.twig', [
            'reservations' => $mesReservations
        ]);
    }
    
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
#[IsGranted('ROLE_ADMIN')]
public function adminDashboard(UserRepository $userRepo, TrajetRepository $trajetRepo, ReservationRepository $reservationRepo): Response
{
    // Récupérer tous les utilisateurs
    $users = $userRepo->findAll();
    $trajets = $trajetRepo->findAll();
    
    // Compter les conducteurs (ceux qui ont ROLE_CONDUCTEUR)
    $conducteurs = 0;
    $passagers = 0;
    $admins = 0;
    
    foreach ($users as $user) {
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles)) {
            $admins++;
        }
        if (in_array('ROLE_CONDUCTEUR', $roles)) {
            $conducteurs++;
        }
        if (in_array('ROLE_PASSAGER', $roles)) {
            $passagers++;
        }
    }
    
    // Compter les trajets en attente
    $trajetsEnAttente = 0;
    $trajetsValides = 0;
    foreach ($trajets as $trajet) {
        if ($trajet->getStatut() === 'EN_ATTENTE') {
            $trajetsEnAttente++;
        } elseif ($trajet->getStatut() === 'VALIDE') {
            $trajetsValides++;
        }
    }
    
    $stats = [
        'totalUsers' => count($users),
        'totalConducteurs' => $conducteurs,
        'totalPassagers' => $passagers,
        'totalAdmins' => $admins,
        'totalTrajets' => count($trajets),
        'totalReservations' => count($reservationRepo->findAll()),
        'trajetsEnAttente' => $trajetsEnAttente,
        'trajetsValides' => $trajetsValides,
        'reclamationsEnAttente' => 0,
        'usersBloques' => 0,
    ];
    
    return $this->render('dashboard/admin.html.twig', [
        'stats' => $stats
    ]);
}
}