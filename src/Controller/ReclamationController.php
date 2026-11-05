<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\User;
use App\Form\ReclamationType;
use App\Repository\ReclamationRepository;
use App\Repository\TrajetRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reclamation')]
class ReclamationController extends AbstractController
{
    #[Route('/nouvelle', name: 'reclamation_new')]
    #[IsGranted('ROLE_USER')]
    //bech tasn3elna new reclamation
    public function new(Request $request, EntityManagerInterface $em, TrajetRepository $trajetRepo, UserRepository $userRepo): Response
    {
        $user = $this->getUser();
        
        $reclamation = new Reclamation();
        $reclamation->setUser($user);
        
        // Récupérer tous les trajets validés (sans filtre JSON_CONTAINS)
        $trajetsDisponibles = $trajetRepo->findBy(['statut' => 'VALIDE'], ['dateDepart' => 'ASC']);
        
        // Récupérer tous les conducteurs (méthode alternative sans JSON_CONTAINS)
        $allUsers = $userRepo->findAll();
        $conducteursDisponibles = [];
        
        foreach ($allUsers as $utilisateur) {
            $roles = $utilisateur->getRoles();
            if (in_array('ROLE_CONDUCTEUR', $roles)) {
                $conducteursDisponibles[] = $utilisateur;
            }
        }
        
        $form = $this->createForm(ReclamationType::class, $reclamation, [
            'trajets_disponibles' => $trajetsDisponibles,
            'conducteurs_disponibles' => $conducteursDisponibles,
        ]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $reclamation->setStatut('EN_ATTENTE');
            $reclamation->setDateCreation(new \DateTime());
            
            $em->persist($reclamation);
            $em->flush();
            
            $this->addFlash('success', 'Votre réclamation a été envoyée avec succès. Notre équipe la traitera dans les plus brefs délais.');
            return $this->redirectToRoute('reclamation_list');
        }
        
        return $this->render('reclamation/new.html.twig', [
            'form' => $form->createView(),
            'trajets' => $trajetsDisponibles,
        ]);
    }
    
    #[Route('/mes-reclamations', name: 'reclamation_list')]
    #[IsGranted('ROLE_USER')]
    public function list(ReclamationRepository $reclamationRepo): Response
    {
        $user = $this->getUser();
        $reclamations = $reclamationRepo->findBy(
            ['user' => $user],
            ['dateCreation' => 'DESC']
        );
        
        $stats = [
            'total' => count($reclamations),
            'enAttente' => count(array_filter($reclamations, fn($r) => $r->getStatut() === 'EN_ATTENTE')),
            'traitees' => count(array_filter($reclamations, fn($r) => $r->getStatut() === 'TRAITEE')),
        ];
        
        return $this->render('reclamation/list.html.twig', [
            'reclamations' => $reclamations,
            'stats' => $stats,
        ]);
    }
    
    #[Route('/voir/{id}', name: 'reclamation_show')]
    #[IsGranted('ROLE_USER')]
    //affiche les detaills 
    public function show(Reclamation $reclamation): Response
    {
        if ($reclamation->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir cette réclamation.');
        }
        
        return $this->render('reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }
}