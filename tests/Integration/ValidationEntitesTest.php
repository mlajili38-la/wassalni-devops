<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\Vehicule;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Verifie les contraintes de validation des entites avec le vrai validateur Symfony.
 * Ces tests protegent contre les erreurs 500 "Unknown named parameter $message"
 * detectees par PHPStan sur User (Length) et Vehicule (Range).
 */
class ValidationEntitesTest extends KernelTestCase
{
    private function messages(object $entite, string $propriete): array
    {
        self::bootKernel();
        $violations = self::getContainer()->get(ValidatorInterface::class)->validateProperty($entite, $propriete);

        return array_map(static fn ($v) => $v->getMessage(), iterator_to_array($violations));
    }

    public function testVehiculeRefuseNombreDePlacesHorsLimites(): void
    {
        $vehicule = (new Vehicule())->setNbPlaces(12);

        self::assertContains('Le nombre de places doit être entre 1 et 9', $this->messages($vehicule, 'nbPlaces'));
    }

    public function testUtilisateurRefuseMotDePasseTropCourt(): void
    {
        $user = (new User())->setPassword('abc');

        self::assertContains('Le mot de passe doit faire au moins 6 caractères', $this->messages($user, 'password'));
    }
}
