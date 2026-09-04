<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * @param Uuid[] $publicationIds
     * @return AuditLog[]
     */
    public function findModerationHistoryForPublications(array $publicationIds): array
    {
        if ($publicationIds === []) {
            return [];
        }

        return $this->createQueryBuilder('audit')
            ->addSelect('moderator')
            ->join('audit.user', 'moderator')
            ->andWhere('audit.targetType = :targetType')
            ->andWhere('audit.targetId IN (:publicationIds)')
            ->andWhere('audit.action IN (:actions)')
            ->setParameter('targetType', AuditLog::TARGET_TYPE_PUBLICATION)
            ->setParameter('publicationIds', $publicationIds)
            ->setParameter('actions', [AuditLog::ACTION_REPORT_ACCEPTED, AuditLog::ACTION_REPORT_REJECTED])
            ->orderBy('audit.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return AuditLog[] Returns an array of AuditLog objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?AuditLog
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
