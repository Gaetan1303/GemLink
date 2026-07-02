<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    /**
     * CA-2 / CA-3 : retrouve un token de reset valide (non utilisé, non expiré) par son hash SHA-256.
     * La valeur brute du token ne transite que dans l'URL du lien email ; on ne stocke que le hash.
     */
    public function findValidByHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->createQueryBuilder('prt')
            ->andWhere('prt.token = :hash')
            ->andWhere('prt.used = false')
            ->andWhere('prt.expiresAt > :now')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }
}