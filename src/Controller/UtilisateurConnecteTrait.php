<?php

namespace App\Controller;

use App\Entity\User;

/**
 * Retourne l'utilisateur connecte avec son type exact (App\Entity\User).
 *
 * AbstractController::getUser() renvoie un UserInterface|null : appeler directement
 * getReservations() ou setPassager($this->getUser()) n'est pas sur pour l'analyse statique.
 * Ce helper verifie le type une fois et refuse l'acces sinon.
 */
trait UtilisateurConnecteTrait
{
    private function utilisateurConnecte(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non connecte.');
        }

        return $user;
    }
}
