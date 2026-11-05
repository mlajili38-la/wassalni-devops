<?php

namespace App\Controller;

use App\Repository\TrajetRepository;
use App\Repository\ReservationRepository;
use App\Repository\VehiculeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\AvisRepository;
#[Route('/conducteur')]
#[IsGranted('ROLE_CONDUCTEUR')]
class ConducteurController extends AbstractController
{
        //athi ele bech taffichi el dashbord (derniers trajet,demandes recentes ,note moyenne)
        #[Route('/dashboard', name: 'conducteur_dashboard')]
        public function dashboard(
            TrajetRepository $trajetRepo,
            ReservationRepository $reservationRepo,
            VehiculeRepository $vehiculeRepo,
            AvisRepository $avisRepo 
        ): Response {
            $user = $this->getUser();
            $mesTrajets = $trajetRepo->findBy(['conducteur' => $user]);
            $mesVehicules = $vehiculeRepo->findBy(['proprietaire' => $user]);
            
            $totalReservations = 0;
            $trajetsActifs = 0;
            
            foreach ($mesTrajets as $trajet) {
                $totalReservations += count($trajet->getReservations());
                if ($trajet->getStatut() === 'VALIDE' && $trajet->getDateDepart() > new \DateTime()) {
                    $trajetsActifs++;
                }
            }
            
            // Tableau des statistiques
            $stats = [
                'totalTrajets' => count($mesTrajets),
                'totalVehicules' => count($mesVehicules),
                'totalReservations' => $totalReservations,
                'trajetsActifs' => $trajetsActifs,
            ];
            
            // Derniers trajets (limité à 4)
            $derniersTrajets = $trajetRepo->findBy(
                ['conducteur' => $user],
                ['dateCreation' => 'DESC'],
                4
            );
            
            // Demandes récentes
            $demandesRecentes = $reservationRepo->createQueryBuilder('r')
                ->join('r.trajet', 't')
                ->where('t.conducteur = :user')
                ->andWhere('r.statut = :statut')
                ->setParameter('user', $user)
                ->setParameter('statut', 'CONFIRMEE')
                ->orderBy('r.dateReservation', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
            
            // ✅ Récupérer les avis reçus par le conducteur
            $avisRecus = $avisRepo->findBy(
                ['conducteur' => $user],
                ['dateCreation' => 'DESC']
            );
            
            // Calculer la note moyenne
            $noteMoyenne = 0;
            $nombreAvis = count($avisRecus);
            if ($nombreAvis > 0) {
                $totalNotes = 0;
                foreach ($avisRecus as $avis) {
                    $totalNotes += $avis->getNote();
                }
                $noteMoyenne = round($totalNotes / $nombreAvis, 1);
            }
            
            return $this->render('conducteur/dashboard.html.twig', [
                'stats' => $stats,
                'derniersTrajets' => $derniersTrajets,
                'demandesRecentes' => $demandesRecentes,
                'avisRecus' => $avisRecus,        // ← AJOUTER CETTE LIGNE
                'noteMoyenne' => $noteMoyenne,    // ← AJOUTER CETTE LIGNE
                'nombreAvis' => $nombreAvis,      // ← AJOUTER CETTE LIGNE
            ]);
        }
    #[Route('/mes-trajets', name: 'conducteur_mes_trajets')]
    public function mesTrajets(TrajetRepository $trajetRepo): Response
    {
        $trajets = $trajetRepo->findBy(
            ['conducteur' => $this->getUser()],
            ['dateDepart' => 'DESC']
        );
        
        return $this->render('conducteur/mes_trajets.html.twig', [
            'trajets' => $trajets
        ]);
    }
    //Afichhe toutes les demandes des resvation pour ses trajets
    #[Route('/demandes', name: 'conducteur_demandes')]
    public function demandes(ReservationRepository $reservationRepo): Response
    {
        $user = $this->getUser();
        $demandes = $reservationRepo->createQueryBuilder('r')
            ->join('r.trajet', 't')
            ->where('t.conducteur = :user')
            ->andWhere('r.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', 'CONFIRMEE')
            ->orderBy('r.dateReservation', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('conducteur/demandes.html.twig', [
            'demandes' => $demandes
        ]);
    }
    //bech n'affichiw l'avis 
    #[Route('/mes-avis', name: 'conducteur_mes_avis')]
public function mesAvis(AvisRepository $avisRepo): Response
{
    $user = $this->getUser();
    
    $avis = $avisRepo->findBy(
        ['conducteur' => $user],
        ['dateCreation' => 'DESC']
    );
    
    // Calculer la note moyenne
    $noteMoyenne = 0;
    if (count($avis) > 0) {
        $totalNotes = 0;
        foreach ($avis as $a) {
            $totalNotes += $a->getNote();
        }
        $noteMoyenne = round($totalNotes / count($avis), 1);
    }
    
    return $this->render('conducteur/mes_avis.html.twig', [
        'avis' => $avis,
        'noteMoyenne' => $noteMoyenne,
        'nombreAvis' => count($avis),
    ]);
}
    
}