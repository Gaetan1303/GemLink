<?php

namespace App\Repository;

use App\Entity\Badge;
use App\Entity\Pierre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Badge>
 */
class BadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Badge::class);
    }

    /** @return list<Badge> */
    public function findAutomaticBadges(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.conditionType IN (:types)')
            ->setParameter('types', [Badge::CONDITION_POST_COUNT, Badge::CONDITION_VALIDATION_COUNT, Badge::CONDITION_STONE_IDENTIFICATION_COUNT, Badge::CONDITION_MINERAL_IDENTIFICATION_COUNT])
            ->getQuery()->getResult();
    }

    public function findMineralIdentificationBadge(Pierre $pierre): ?Badge
    {
        return $this->findOneBy(['conditionType' => Badge::CONDITION_MINERAL_IDENTIFICATION_COUNT, 'pierre' => $pierre]);
    }

    //    /**
    //     * @return Badge[] Returns an array of Badge objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Badge
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
