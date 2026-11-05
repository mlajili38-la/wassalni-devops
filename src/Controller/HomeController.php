<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    //ychouf el user mconnect bech yhezou  lel dashbord el 5assa bihe 
    public function index(): Response
    {
        if ($this->getUser()) {
            $user = $this->getUser();
            $roles = $user->getRoles();
            
            if (in_array('ROLE_ADMIN', $roles)) {
                return $this->redirectToRoute('admin_dashboard');
            } elseif (in_array('ROLE_CONDUCTEUR', $roles)) {
                return $this->redirectToRoute('conducteur_dashboard');
            } elseif (in_array('ROLE_PASSAGER', $roles)) {
                return $this->redirectToRoute('trajet_search');
            }
        }
        
        return $this->render('home/index.html.twig');
    }
}