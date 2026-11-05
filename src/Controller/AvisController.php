<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Trajet;
use App\Form\AvisType;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/avis')]
class AvisController extends AbstractController
{
    #[Route('/nouveau/{id}', name: 'avis_new')]
    #[IsGranted('ROLE_PASSAGER')]
    public function new(Trajet $trajet, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        // Vérifier que le passager a bien réservé ce trajet
        $aReserve = false;
        foreach ($user->getReservations() as $reservation) {
            if ($reservation->getTrajet()->getId() === $trajet->getId() && $reservation->getStatut() === 'CONFIRMEE') {
                $aReserve = true;
                break;
            }
        }
        
        if (!$aReserve) {
            $this->addFlash('error', 'Vous ne pouvez pas laisser d\'avis sur ce trajet.');
            return $this->redirectToRoute('mes_reservations');
        }
        
        // Vérifier que le passager n'a pas déjà laissé un avis
        $existingAvis = $em->getRepository(Avis::class)->findOneBy([
            'passager' => $user,
            'trajet' => $trajet
        ]);
        
        if ($existingAvis) {
            $this->addFlash('error', 'Vous avez déjà laissé un avis pour ce trajet.');
            return $this->redirectToRoute('mes_reservations');
        }
        
        $avis = new Avis();
        $avis->setPassager($user);
        $avis->setConducteur($trajet->getConducteur());
        $avis->setTrajet($trajet);
        
        $form = $this->createForm(AvisType::class, $avis);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($avis);
            $em->flush();
            
            $this->addFlash('success', 'Merci pour votre avis !');
            return $this->redirectToRoute('mes_reservations');
        }
        
        return $this->render('avis/new.html.twig', [
            'form' => $form->createView(),
            'trajet' => $trajet,
            'conducteur' => $trajet->getConducteur(),
        ]);
    }
    //ya5ou kes avis mte3 el conducteur 
    // affiche sa note moyenne et le details des avis
    #[Route('/conducteur/{id}', name: 'avis_conducteur')]
    public function conducteurAvis(User $conducteur, AvisRepository $avisRepo): Response
    {
        $avis = $avisRepo->findBy(
            ['conducteur' => $conducteur],
            ['dateCreation' => 'DESC']
        );
        
        return $this->render('avis/conducteur.html.twig', [
            'conducteur' => $conducteur,
            'avis' => $avis,
            'noteMoyenne' => $conducteur->getNoteMoyenne(),
            'nombreAvis' => $conducteur->getNombreAvis(),
        ]);
    }
}