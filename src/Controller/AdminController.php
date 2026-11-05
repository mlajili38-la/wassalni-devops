<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Trajet;
use App\Entity\Reclamation;
use App\Repository\TrajetRepository;
use App\Repository\UserRepository;
use App\Repository\ReservationRepository;
use App\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    //ychargi les utilisateurs ,trajet et reclmation depuis la bases des données
    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(
        UserRepository $userRepo,
        TrajetRepository $trajetRepo,
        ReservationRepository $reservationRepo,
        ReclamationRepository $reclamationRepo
    ): Response {
        // Récupérer tous les utilisateurs
        $users = $userRepo->findAll();
        
        // Compter manuellement les rôles
        $totalConducteurs = 0;
        $totalPassagers = 0;
        $totalAdmins = 0;
        
        foreach ($users as $user) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $totalAdmins++;
            }
            if (in_array('ROLE_CONDUCTEUR', $roles)) {
                $totalConducteurs++;
            }
            if (in_array('ROLE_PASSAGER', $roles)) {
                $totalPassagers++;
            }
        }
        
        // Compter les trajets
        $trajets = $trajetRepo->findAll();
        $trajetsEnAttente = 0;
        $trajetsValides = 0;
        
        foreach ($trajets as $trajet) {
            if ($trajet->getStatut() === 'EN_ATTENTE') {
                $trajetsEnAttente++;
            } elseif ($trajet->getStatut() === 'VALIDE') {
                $trajetsValides++;
            }
        }
        
        // Compter les réclamations en attente
        $reclamations = $reclamationRepo->findAll();
        $reclamationsEnAttente = 0;
        
        foreach ($reclamations as $reclamation) {
            if ($reclamation->getStatut() === 'EN_ATTENTE') {
                $reclamationsEnAttente++;
            }
        }
        
        $stats = [
            'totalUsers' => count($users),
            'totalConducteurs' => $totalConducteurs,
            'totalPassagers' => $totalPassagers,
            'totalAdmins' => $totalAdmins,
            'totalTrajets' => count($trajets),
            'totalReservations' => count($reservationRepo->findAll()),
            'trajetsEnAttente' => $trajetsEnAttente,
            'trajetsValides' => $trajetsValides,
            'reclamationsEnAttente' => $reclamationsEnAttente,
            'usersBloques' => 0,
        ];
        
        // Derniers utilisateurs
        $derniersUtilisateurs = $userRepo->findBy([], ['id' => 'DESC'], 5);
        $derniersTrajets = $trajetRepo->findBy([], ['id' => 'DESC'], 5);
        
        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'derniersUtilisateurs' => $derniersUtilisateurs,
            'derniersTrajets' => $derniersTrajets,
        ]);
    }
    //yaffichi tous les utilisateurs 
    
    #[Route('/utilisateurs', name: 'admin_users')]
    public function users(UserRepository $userRepo): Response
    {
        $users = $userRepo->findAll();
        
        return $this->render('admin/users.html.twig', [
            'users' => $users
        ]);
    }
    //ya5ou l'id w yaktivilou el compte te3ou  
    #[Route('/utilisateur/activer/{id}', name: 'admin_user_activate')]
    public function activateUser(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatut('ACTIF');
        $em->flush();
        
        $this->addFlash('success', 'Utilisateur activé avec succès.');
        return $this->redirectToRoute('admin_users');
    }
    //yeblockii user
    #[Route('/utilisateur/bloquer/{id}', name: 'admin_user_block')]
    public function blockUser(User $user, EntityManagerInterface $em): Response
    {
        $user->setStatut('BLOQUE');
        $em->flush();
        
        $this->addFlash('success', 'Utilisateur bloqué avec succès.');
        return $this->redirectToRoute('admin_users');
    }
    
    #[Route('/utilisateur/supprimer/{id}', name: 'admin_user_delete')]
    public function deleteUser(User $user, EntityManagerInterface $em): Response
    {
        $email = $user->getEmail();
        $em->remove($user);
        $em->flush();
        
        $this->addFlash('success', 'Utilisateur ' . $email . ' supprimé avec succès.');
        return $this->redirectToRoute('admin_users');
    }
    //affiche tous les trajets
    #[Route('/trajets', name: 'admin_trajets')]
    public function trajets(TrajetRepository $trajetRepo): Response
    {
        $trajets = $trajetRepo->findAll();
        
        return $this->render('admin/trajets.html.twig', [
            'trajets' => $trajets
        ]);
    }
    //changer le statut en atten en "en valide"
    #[Route('/trajet/valider/{id}', name: 'admin_trajet_validate')]
    public function validateTrajet(Trajet $trajet, EntityManagerInterface $em): Response
    {
        $trajet->setStatut('VALIDE');
        $em->flush();
        
        $this->addFlash('success', 'Trajet validé avec succès.');
        return $this->redirectToRoute('admin_trajets');
    }
    //dellete deffinitivement un trajet 
    #[Route('/trajet/supprimer/{id}', name: 'admin_trajet_delete')]
    public function deleteTrajet(Trajet $trajet, EntityManagerInterface $em): Response
    {
        $em->remove($trajet);
        $em->flush();
        
        $this->addFlash('success', 'Trajet supprimé avec succès.');
        return $this->redirectToRoute('admin_trajets');
    }
    //affiche touttes les reclamations des utilisateurs 
    #[Route('/reclamations', name: 'admin_reclamations')]
    public function reclamations(ReclamationRepository $reclamationRepo): Response
    {
        $reclamations = $reclamationRepo->findAll();
        
        return $this->render('admin/reclamations.html.twig', [
            'reclamations' => $reclamations
        ]);
    }
    //Récupère la réponse de l'admin, l'enregistre dans la réclamation et marque comme "traitée".
    #[Route('/reclamation/traiter/{id}', name: 'admin_reclamation_process')]
    public function processReclamation(Request $request, Reclamation $reclamation, EntityManagerInterface $em): Response
    {
        $reponse = $request->request->get('reponse');
        
        if ($reponse) {
            $reclamation->setReponse($reponse);
            $reclamation->setStatut('TRAITEE');
            $reclamation->setDateTraitement(new \DateTime());
            $em->flush();
            
            $this->addFlash('success', 'Réclamation traitée avec succès.');
        } else {
            $this->addFlash('error', 'Veuillez saisir une réponse.');
        }
        
        return $this->redirectToRoute('admin_reclamations');
    }
    
}