<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Utilisateurs possedant un role donne (ex. ROLE_CONDUCTEUR).
     *
     * La colonne roles est un tableau JSON (["ROLE_CONDUCTEUR"]) : JSON_CONTAINS n'existe pas en DQL,
     * on recherche donc le role entoure de guillemets dans le texte JSON (portable MySQL/PostgreSQL/SQLite).
     *
     * @return list<User>
     */
    public function findByRoles(string $role): array
    {
        /** @var list<User> $utilisateurs */
        $utilisateurs = $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"'.$role.'"%')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $utilisateurs;
    }
}
