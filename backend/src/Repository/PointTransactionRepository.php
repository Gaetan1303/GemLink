<?php

namespace App\Repository;

use App\Entity\PointTransaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<PointTransaction> */
class PointTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PointTransaction::class);
    }

    public function hasSource(User $user, string $action, Uuid $sourceId): bool
    {
        return $this->count(['user' => $user, 'action' => $action, 'sourceId' => $sourceId]) > 0;
    }

    /** @return PointTransaction[] */
    public function findHistoryForUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('transaction')
            ->andWhere('transaction.user = :user')
            ->setParameter('user', $user)
            ->orderBy('transaction.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
