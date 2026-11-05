<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicule;
use App\Service\MailerService;
use App\Repository\UserRepository;  // ← AJOUTER CETTE LIGNE
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationWizardController extends AbstractController
{
    //enregistre tous les données 
    #[Route('/inscription/etape/{step}', name: 'app_register_wizard', defaults: ['step' => 1])]
    public function registerWizard(
        Request $request, 
        int $step,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        MailerService $mailerService
    ): Response {
        $session = $request->getSession();
        $data = $session->get('registration_data', []);
        $userType = $data['userType'] ?? null;
        $maxStep = ($userType === 'conducteur') ? 5 : 4;
        
        if ($step > $maxStep) {
            return $this->redirectToRoute('app_register_wizard', ['step' => $maxStep]);
        }

        if ($request->isMethod('POST')) {
            switch ($step) {
                case 1:
                    $userType = $request->request->get('user_type');
                    if ($userType) {
                        $data['userType'] = $userType;
                        $session->set('registration_data', $data);
                        return $this->redirectToRoute('app_register_wizard', ['step' => 2]);
                    }
                    $this->addFlash('error', 'Veuillez sélectionner un type de compte');
                    break;
                    
                case 2:
                    $email = $request->request->get('email');
                    $password = $request->request->get('password');
                    $passwordConfirm = $request->request->get('password_confirm');
                    
                    if (!$email) {
                        $this->addFlash('error', 'L\'email est obligatoire');
                    } elseif (!$password) {
                        $this->addFlash('error', 'Le mot de passe est obligatoire');
                    } elseif ($password !== $passwordConfirm) {
                        $this->addFlash('error', 'Les mots de passe ne correspondent pas');
                    } elseif (strlen($password) < 6) {
                        $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères');
                    } else {
                        $data['email'] = $email;
                        $data['password'] = $password;
                        $session->set('registration_data', $data);
                        return $this->redirectToRoute('app_register_wizard', ['step' => 3]);
                    }
                    break;
                    
                case 3:
                    $nom = $request->request->get('nom');
                    $prenom = $request->request->get('prenom');
                    $telephone = $request->request->get('telephone');
                    $adresse = $request->request->get('adresse');
                    
                    if (!$nom) {
                        $this->addFlash('error', 'Le nom est obligatoire');
                    } elseif (!$prenom) {
                        $this->addFlash('error', 'Le prénom est obligatoire');
                    } elseif (!$telephone) {
                        $this->addFlash('error', 'Le téléphone est obligatoire');
                    } else {
                        $data['nom'] = $nom;
                        $data['prenom'] = $prenom;
                        $data['telephone'] = $telephone;
                        $data['adresse'] = $adresse;
                        $session->set('registration_data', $data);
                        
                        if ($userType === 'conducteur') {
                            return $this->redirectToRoute('app_register_wizard', ['step' => 4]);
                        } else {
                            return $this->createUser($data, $session, $em, $passwordHasher, $mailerService);
                        }
                    }
                    break;
                    
                case 4:
                    $marque = $request->request->get('marque');
                    $modele = $request->request->get('modele');
                    $immatriculation = $request->request->get('immatriculation');
                    $nbPlaces = $request->request->get('nbPlaces');
                    $couleur = $request->request->get('couleur');
                    
                    if (!$marque) {
                        $this->addFlash('error', 'La marque est obligatoire');
                    } elseif (!$modele) {
                        $this->addFlash('error', 'Le modèle est obligatoire');
                    } elseif (!$immatriculation) {
                        $this->addFlash('error', 'L\'immatriculation est obligatoire');
                    } elseif (!$nbPlaces) {
                        $this->addFlash('error', 'Le nombre de places est obligatoire');
                    } else {
                        $data['marque'] = $marque;
                        $data['modele'] = $modele;
                        $data['immatriculation'] = $immatriculation;
                        $data['nbPlaces'] = $nbPlaces;
                        $data['couleur'] = $couleur;
                        $session->set('registration_data', $data);
                        return $this->createUser($data, $session, $em, $passwordHasher, $mailerService);
                    }
                    break;
            }
        }

        return $this->render("registration/step$step.html.twig", [
            'step' => $step,
            'totalSteps' => $maxStep,
            'data' => $data
        ]);
    }

    private function createUser(array $data, $session, $em, $passwordHasher, $mailerService): Response
    {
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            $session->remove('registration_data');
            $this->addFlash('error', 'Cet email est déjà utilisé.');
            return $this->redirectToRoute('app_register_wizard', ['step' => 1]);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setNom($data['nom'] ?? '');
        $user->setPrenom($data['prenom'] ?? '');
        $user->setTelephone($data['telephone'] ?? '');
        $user->setStatut('ACTIF');
        $user->setIsVerified(false);
        
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        $user->generateVerificationToken();
        
        if ($data['userType'] === 'conducteur') {
            $user->setRoles(['ROLE_CONDUCTEUR']);
            
            if (isset($data['marque']) && isset($data['modele'])) {
                $vehicule = new Vehicule();
                $vehicule->setMarque($data['marque']);
                $vehicule->setModele($data['modele']);
                $vehicule->setImmatriculation($data['immatriculation']);
                $vehicule->setNbPlaces($data['nbPlaces'] ?? 4);
                $vehicule->setCouleur($data['couleur'] ?? null);
                $vehicule->setProprietaire($user);
                
                $em->persist($vehicule);
            }
        } else {
            $user->setRoles(['ROLE_PASSAGER']);
        }

        $em->persist($user);
        $em->flush();
        
        // Stocker l'email dans la session
        $session->set('last_registered_email', $user->getEmail());
        
        $mailerService->sendVerificationEmail($user);
        $session->remove('registration_data');
        
        return $this->redirectToRoute('app_verify_waiting');
    }
}