<?php

namespace App\Controller;

use App\Entity\Vehicule;
use App\Form\VehiculeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/vehicule')]
#[IsGranted('ROLE_CONDUCTEUR')]
class VehiculeController extends AbstractController
{
    #[Route('/mes-vehicules', name: 'vehicule_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $vehicules = $em->getRepository(Vehicule::class)->findBy([
            'proprietaire' => $this->getUser()
        ]);
        
        return $this->render('vehicule/index.html.twig', [
            'vehicules' => $vehicules
        ]);
    }
    
    #[Route('/nouveau', name: 'vehicule_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $vehicule = new Vehicule();
        $form = $this->createForm(VehiculeType::class, $vehicule);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $vehicule->setProprietaire($this->getUser());
            $vehicule->setDateAjout(new \DateTime());
            
            $em->persist($vehicule);
            $em->flush();
            
            $this->addFlash('success', 'Véhicule ajouté avec succès !');
            return $this->redirectToRoute('vehicule_index');
        }
        
        return $this->render('vehicule/new.html.twig', [
            'form' => $form->createView()
        ]);
    }
    
    #[Route('/modifier/{id}', name: 'vehicule_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Vehicule $vehicule, EntityManagerInterface $em): Response
    {
        if ($vehicule->getProprietaire() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        $form = $this->createForm(VehiculeType::class, $vehicule);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Véhicule modifié avec succès !');
            return $this->redirectToRoute('vehicule_index');
        }
        
        return $this->render('vehicule/edit.html.twig', [
            'form' => $form->createView(),
            'vehicule' => $vehicule
        ]);
    }
    
    #[Route('/supprimer/{id}', name: 'vehicule_delete', methods: ['POST'])]
    public function delete(Request $request, Vehicule $vehicule, EntityManagerInterface $em): Response
    {
        if ($vehicule->getProprietaire() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        
        if ($this->isCsrfTokenValid('delete' . $vehicule->getId(), $request->request->get('_token'))) {
            $em->remove($vehicule);
            $em->flush();
            $this->addFlash('success', 'Véhicule supprimé avec succès !');
        }
        
        return $this->redirectToRoute('vehicule_index');
    }
}