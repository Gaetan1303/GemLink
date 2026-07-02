<?php

namespace App\Repository;

use App\Entity\User;

use App\Entity\RefreshToken;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * CA-1 : retrouve un refresh token valide (non révoqué, non expiré) par son hash SHA-256.
     * Le cookie httpOnly ne contient que la valeur brute ; on la hash avant comparaison
     * afin de ne jamais stocker la valeur brute en base.
     */
    public function findValidByHash(string $tokenHash): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->andWhere('rt.tokenHash = :hash')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }
        public function revokeAllActiveForUser(User $user): void
    {
        $now = new DateTimeImmutable();
 
        $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.revokedAt', ':now')
            ->where('rt.user = :user')
            ->andWhere('rt.revokedAt IS NULL')
            ->andWhere('rt.expiresAt > :now2')
            ->setParameter('now', $now)
            ->setParameter('user', $user)
            ->setParameter('now2', $now)
            ->getQuery()
            ->execute();
    }

}