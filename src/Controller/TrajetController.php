<?php

namespace App\Controller;

use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Form\TrajetType;
use App\Repository\TrajetRepository;
use App\Repository\VehiculeRepository;  // ← AJOUTER CETTE LIGNE
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/trajet')]
class TrajetController extends AbstractController
{
    #[Route('/recherche', name: 'trajet_search')]
    public function search(Request $request, TrajetRepository $trajetRepo): Response
    {
        $queryBuilder = $trajetRepo->createQueryBuilder('t')
            ->where('t.statut = :statut')
            ->setParameter('statut', 'VALIDE')
            ->andWhere('t.placesDisponibles > 0')
            ->orderBy('t.dateDepart', 'ASC');
        
        // Filtre ville départ
        if ($request->query->get('villeDepart')) {
            $queryBuilder->andWhere('t.villeDepart = :villeDepart')
                ->setParameter('villeDepart', $request->query->get('villeDepart'));
        }
        
        // Filtre destination
        if ($request->query->get('destination')) {
            $queryBuilder->andWhere('t.destination = :destination')
                ->setParameter('destination', $request->query->get('destination'));
        }
        
        // Filtre date - correction : utiliser BETWEEN au lieu de DATE()
        if ($request->query->get('dateDepart')) {
            $date = new \DateTime($request->query->get('dateDepart'));
            $dateDebut = clone $date;
            $dateDebut->setTime(0, 0, 0);
            $dateFin = clone $date;
            $dateFin->setTime(23, 59, 59);
            
            $queryBuilder->andWhere('t.dateDepart BETWEEN :dateDebut AND :dateFin')
                ->setParameter('dateDebut', $dateDebut)
                ->setParameter('dateFin', $dateFin);
        }
        
        $trajets = $queryBuilder->getQuery()->getResult();
        
        return $this->render('trajet/search.html.twig', [
            'trajets' => $trajets,
            'filters' => $request->query->all()
        ]);
    }
    #[Route('/nouveau', name: 'trajet_new')]
#[IsGranted('ROLE_CONDUCTEUR')]
public function new(Request $request, EntityManagerInterface $em, VehiculeRepository $vehiculeRepo): Response
{
    $user = $this->getUser();
    
    // Vérifier si le conducteur a des véhicules
    $vehicules = $user->getVehicules();
    if ($vehicules->isEmpty()) {
        $this->addFlash('error', 'Vous devez d\'abord ajouter un véhicule.');
        return $this->redirectToRoute('vehicule_new');
    }
    
    if ($request->isMethod('POST')) {
        // Récupération des données POST
        $villeDepart = trim($request->request->get('villeDepart', ''));
        $destination = trim($request->request->get('destination', ''));
        $dateDepart = $request->request->get('dateDepart', '');
        $dateArrivee = $request->request->get('dateArrivee', '');
        $prix = $request->request->get('prix', 0);
        $vehiculeId = $request->request->get('vehicule', 0);
        $description = $request->request->get('description', '');
        
        // Validation des champs obligatoires
        $errors = [];
        
        if (empty($villeDepart)) {
            $errors[] = 'La ville de départ est obligatoire';
        }
        if (empty($destination)) {
            $errors[] = 'La destination est obligatoire';
        }
        if (empty($dateDepart)) {
            $errors[] = 'La date de départ est obligatoire';
        }
        if (empty($dateArrivee)) {
            $errors[] = 'La date d\'arrivée est obligatoire';
        }
        if (empty($prix) || $prix <= 0) {
            $errors[] = 'Le prix doit être positif';
        }
        if (empty($vehiculeId)) {
            $errors[] = 'Veuillez sélectionner un véhicule';
        }
        
        // Conversion des dates (format français jj/mm/aaaa)
        $dateDepartObj = null;
        $dateArriveeObj = null;
        
        if (!empty($dateDepart)) {
            $parts = explode('/', $dateDepart);
            if (count($parts) === 3) {
                $dateDepartObj = \DateTime::createFromFormat('d/m/Y', $dateDepart);
                if ($dateDepartObj) {
                    $dateDepartObj->setTime(0, 0, 0);
                }
            }
        }
        
        if (!empty($dateArrivee)) {
            $parts = explode('/', $dateArrivee);
            if (count($parts) === 3) {
                $dateArriveeObj = \DateTime::createFromFormat('d/m/Y', $dateArrivee);
                if ($dateArriveeObj) {
                    $dateArriveeObj->setTime(0, 0, 0);
                }
            }
        }
        
        // Validation des dates
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        if ($dateDepartObj && $dateDepartObj < $today) {
            $errors[] = 'La date de départ doit être dans le futur';
        }
        
        if ($dateDepartObj && $dateArriveeObj && $dateArriveeObj < $dateDepartObj) {
            $errors[] = 'La date d\'arrivée doit être égale ou après la date de départ';
        }
        
        // Récupérer le véhicule
        $vehicule = null;
        if (!empty($vehiculeId)) {
            $vehicule = $vehiculeRepo->find((int)$vehiculeId);
            if (!$vehicule) {
                $errors[] = 'Véhicule non trouvé';
            }
        }
        
        // Si erreurs, afficher et retourner
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('trajet_new');
        }
        
        // Création du trajet
        $trajet = new Trajet();
        $trajet->setVilleDepart($villeDepart);
        $trajet->setDestination($destination);
        $trajet->setDateDepart($dateDepartObj);
        $trajet->setDateArrivee($dateArriveeObj);
        $trajet->setPrix((float)$prix);
        $trajet->setVehicule($vehicule);
        $trajet->setDescription($description);
        $trajet->setConducteur($user);
        $trajet->setStatut('VALIDE'); // Validation automatique pour simplifier, à ajuster selon les besoins
        $trajet->setPlacesDisponibles($vehicule->getNbPlaces());
        $trajet->setDateCreation(new \DateTime());
        
        try {
            $em->persist($trajet);
            $em->flush();
            
            $this->addFlash('success', 'Trajet publié avec succès ! En attente de validation.');
            return $this->redirectToRoute('conducteur_dashboard');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
            return $this->redirectToRoute('trajet_new');
        }
    }
    
    return $this->render('trajet/new.html.twig', [
        'vehicules' => $vehicules
    ]);
}
    
    #[Route('/{id}', name: 'trajet_show', requirements: ['id' => '\d+'])]
    public function show(Trajet $trajet): Response
    {
        return $this->render('trajet/show.html.twig', [
            'trajet' => $trajet
        ]);
    }
}