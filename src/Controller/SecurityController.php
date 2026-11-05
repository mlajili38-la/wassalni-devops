<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;  // ← AJOUTER CETTE LIGNE
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            
            $roles = [$form->get('roles')->getData()];
            $user->setRoles($roles);
            
            $em->persist($user);
            $em->flush();
            
            $this->addFlash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }
        
        return $this->render('security/register.html.twig', [
            'form' => $form->createView()
        ]);
    }
    
    #[Route('/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }
        
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        
        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }
    
    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    
    #[Route('/verification-attente', name: 'app_verify_waiting')]
    public function waitingForVerification(): Response
    {
        return $this->render('security/waiting.html.twig');
    }
    
    #[Route('/verify-email', name: 'app_verify_email')]
    public function verifyEmail(Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        $token = $request->query->get('token');
        
        if (!$token) {
            $this->addFlash('error', 'Token de vérification manquant.');
            return $this->redirectToRoute('app_login');
        }
        
        $user = $userRepo->findOneBy(['verificationToken' => $token]);
        
        if (!$user) {
            $this->addFlash('error', 'Token de vérification invalide.');
            return $this->redirectToRoute('app_login');
        }
        
        $now = new \DateTime();
        if ($user->getVerificationTokenExpiresAt() && $user->getVerificationTokenExpiresAt() < $now) {
            $this->addFlash('error', 'Le lien de vérification a expiré.');
            return $this->redirectToRoute('app_login');
        }
        
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);
        $em->flush();
        
        $this->addFlash('success', 'Votre compte a été activé avec succès !');
        return $this->redirectToRoute('app_login');
    }
    
    #[Route('/get-last-user-email', name: 'get_last_user_email')]
    public function getLastUserEmail(Request $request, UserRepository $userRepo): JsonResponse
    {
        $session = $request->getSession();
        $email = $session->get('last_registered_email');
        
        if (!$email) {
            $lastUser = $userRepo->findOneBy([], ['id' => 'DESC']);
            if ($lastUser) {
                $email = $lastUser->getEmail();
            }
        }
        
        return $this->json(['email' => $email]);
    }
    
    #[Route('/check-verification-status', name: 'check_verification_status')]
    public function checkVerificationStatus(Request $request, UserRepository $userRepo): JsonResponse
    {
        $session = $request->getSession();
        $email = $session->get('last_registered_email');
        
        if ($email) {
            $user = $userRepo->findOneBy(['email' => $email]);
            if ($user && $user->isVerified()) {
                return $this->json(['verified' => true]);
            }
        } else {
            $lastUser = $userRepo->findOneBy([], ['id' => 'DESC']);
            if ($lastUser && $lastUser->isVerified()) {
                return $this->json(['verified' => true]);
            }
        }
        
        return $this->json(['verified' => false]);
    }
}