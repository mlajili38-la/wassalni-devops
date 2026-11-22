<?php

namespace App\Tests\Functional;

use App\Entity\Reclamation;
use App\Entity\Trajet;
use App\Entity\User;
use App\Entity\Vehicule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels : requetes HTTP sur l'application Symfony complete
 * (routage, securite, controleurs, Twig) avec un utilisateur connecte et la base MySQL de TEST.
 *
 * Isolation : le noyau n'est pas redemarre entre les requetes (disableReboot) et tout le test
 * s'execute dans une transaction annulee a la fin, la base reste propre.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Garde-fou : refuser de s'executer ailleurs que sur une base de test (*_test).
        $base = (string) $this->em->getConnection()->getDatabase();
        if (!str_ends_with($base, '_test')) {
            self::fail(sprintf('Les tests fonctionnels doivent utiliser une base *_test, base actuelle : "%s".', $base));
        }

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    /**
     * Cree un utilisateur ; roles possibles : ROLE_PASSAGER, ROLE_CONDUCTEUR, ROLE_ADMIN.
     *
     * @param list<string> $roles
     */
    protected function creerUtilisateur(string $email, array $roles = ['ROLE_PASSAGER']): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setNom('Test')
            ->setPrenom('Fonctionnel')
            ->setTelephone('20000000')
            ->setRoles($roles)
            ->setPassword('hash-de-test');
        $this->em->persist($user);

        return $user;
    }

    protected function creerVehicule(User $proprietaire, string $immatriculation = '123TU4567', int $places = 4): Vehicule
    {
        $vehicule = (new Vehicule())
            ->setMarque('Peugeot')
            ->setModele('208')
            ->setImmatriculation($immatriculation)
            ->setNbPlaces($places)
            ->setDateAjout(new \DateTime())
            ->setProprietaire($proprietaire);
        $this->em->persist($vehicule);

        return $vehicule;
    }

    protected function creerTrajet(User $conducteur, int $places = 3, float $prix = 10.0, string $statut = 'VALIDE'): Trajet
    {
        $trajet = (new Trajet())
            ->setVilleDepart('Tunis')
            ->setDestination('Sousse')
            ->setDateDepart(new \DateTime('+1 day'))
            ->setDateArrivee(new \DateTime('+1 day 2 hours'))
            ->setPrix($prix)
            ->setPlacesDisponibles($places)
            ->setStatut($statut)
            ->setDateCreation(new \DateTime())
            ->setConducteur($conducteur)
            ->setVehicule($this->creerVehicule($conducteur));
        $this->em->persist($trajet);

        return $trajet;
    }

    protected function creerReclamation(User $auteur): Reclamation
    {
        $reclamation = (new Reclamation())
            ->setSujet('Retard du conducteur')
            ->setMessage('Le conducteur est arrive avec trente minutes de retard.')
            ->setStatut('EN_ATTENTE')
            ->setDateCreation(new \DateTime())
            ->setUser($auteur);
        $this->em->persist($reclamation);

        return $reclamation;
    }

    /**
     * Ecrit les donnees de test en base puis vide le cache Doctrine :
     * les requetes HTTP relisent alors l'etat reel de la base (comme en production).
     */
    protected function sauvegarder(): void
    {
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * Relit une entite depuis la base apres une requete HTTP.
     *
     * @template T of object
     *
     * @param class-string<T> $classe
     *
     * @return T|null
     */
    protected function relire(string $classe, ?int $id): ?object
    {
        $this->em->clear();

        return $this->em->find($classe, $id);
    }

    protected function connecter(User $user): void
    {
        $this->client->loginUser($user);
    }
}
