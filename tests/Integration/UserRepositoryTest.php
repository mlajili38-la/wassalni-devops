<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Fonctionnalite metier n°2 : gestion des utilisateurs, avec la vraie base MySQL.
 *
 * Anomalie connue (non corrigee, methode non utilisee) : UserRepository::findByRoles()
 * utilise JSON_CONTAINS, fonction inconnue de Doctrine DQL -> QueryException.
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

    public function testEmailUniqueImposeParLaBase(): void
    {
        $this->creerUtilisateur('doublon@test.local');
        $this->em->flush();

        $this->creerUtilisateur('doublon@test.local');
        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }
}
