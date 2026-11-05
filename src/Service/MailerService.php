<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function sendVerificationEmail(User $user): void    {        dump('=== DÉBUT ENVOI EMAIL ===');
        dump('Email destinataire: ' . $user->getEmail());
        dump('Token: ' . $user->getVerificationToken());
        
        $verificationUrl = $this->urlGenerator->generate('app_verify_email', [
            'token' => $user->getVerificationToken()
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        
        dump('URL: ' . $verificationUrl);
        
        $emailContent = $this->twig->render('emails/verification.html.twig', [
            'user' => $user,
            'verificationUrl' => $verificationUrl
        ]);
        
        $email = (new Email())
            ->from('mlajili38@gmail.com')
            ->to($user->getEmail())
            ->subject('Confirmez votre inscription - Wassalni')
            ->html($emailContent);
        
        try {
            $this->mailer->send($email);
            dump('✅ Email envoyé avec succès !');
        } catch (\Exception $e) {
            dump('❌ Erreur: ' . $e->getMessage());
        }
        
        dump('=== FIN ENVOI EMAIL ===');
    }
}