<?php

namespace App\Tests\Integration;

use App\Entity\Trajet;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base des tests d'integration : utilise la vraie base MySQL de TEST
 * (suffixe "_test" ajoute par config/packages/doctrine.yaml, distincte de la base de dev).
 * Chaque test s'execute dans une transaction annulee a la fin : la base reste propre.
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        $this->em->close();
        parent::tearDown();
    }

    protected function creerUtilisateur(string $email, array $roles = ['ROLE_PASSAGER']): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setNom('Test')
            ->setPrenom('Integration')
            ->setTelephone('20000000')
            ->setRoles($roles)
            ->setPassword('hash-de-test');
        $this->em->persist($user);

        return $user;
    }

    protected function creerTrajet(User $conducteur, int $places = 3, float $prix = 10.0): Trajet
    {
        $vehicule = (new Vehicule())
            ->setMarque('Peugeot')
            ->setModele('208')
            ->setImmatriculation('123TU4567')
            ->setNbPlaces(4)
            ->setDateAjout(new \DateTime())
            ->setProprietaire($conducteur);
        $this->em->persist($vehicule);

        $trajet = (new Trajet())
            ->setVilleDepart('Tunis')
            ->setDestination('Sousse')
            ->setDateDepart(new \DateTime('+1 day'))
            ->setDateArrivee(new \DateTime('+1 day 2 hours'))
            ->setPrix($prix)
            ->setPlacesDisponibles($places)
            ->setDateCreation(new \DateTime())
            ->setConducteur($conducteur)
            ->setVehicule($vehicule);
        $this->em->persist($trajet);

        return $trajet;
    }
}
