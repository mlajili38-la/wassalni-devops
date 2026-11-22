<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Fonctionnalite metier n°2 : gestion des utilisateurs, avec la vraie base MySQL.
 */
class UserRepositoryTest extends DatabaseTestCase
{
    public function testUtilisateurEnregistreEtRetrouveParEmail(): void
    {
        $this->creerUtilisateur('admin.repo@test.local', ['ROLE_ADMIN']);
        $this->em->flush();
        $this->em->clear();

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin.repo@test.local']);

        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertSame('ACTIF', $user->getStatut());
    }

    public function testFindByRolesNeRetourneQueLesUtilisateursDuRole(): void
    {
        $this->creerUtilisateur('conducteur.role@test.local', ['ROLE_CONDUCTEUR']);
        $this->creerUtilisateur('passager.role@test.local', ['ROLE_PASSAGER']);
        $this->creerUtilisateur('mixte.role@test.local', ['ROLE_PASSAGER', 'ROLE_CONDUCTEUR']);
        $this->em->flush();

        $conducteurs = self::getContainer()->get(UserRepository::class)->findByRoles('ROLE_CONDUCTEUR');
        $emails = array_map(static fn (User $u): ?string => $u->getEmail(), $conducteurs);

        self::assertContains('conducteur.role@test.local', $emails);
        self::assertContains('mixte.role@test.local', $emails);
        self::assertNotContains('passager.role@test.local', $emails);
    }

    public function testEmailUniqueImposeParLaBase(): void
    {
        $this->creerUtilisateur('doublon@test.local');
        $this->em->flush();

        $this->creerUtilisateur('doublon@test.local');
        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }
}
