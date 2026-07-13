<?php

namespace App\Repository;

use App\Entity\VersionModeleIa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VersionModeleIa>
 */
class VersionModeleIaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VersionModeleIa::class);
    }

    /**
     * La version "active" la plus récente pour un type de modèle donné
     * (YOLO / VIT / CLIP) — plusieurs versions peuvent coexister en base
     * (historique, fine-tuning), une seule doit être utilisée en production.
     */
    public function findActiveByType(string $modelType): ?VersionModeleIa
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.modelType = :modelType')
            ->andWhere('v.status = :status')
            ->setParameter('modelType', $modelType)
            ->setParameter('status', VersionModeleIa::STATUS_ACTIVE)
            ->orderBy('v.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}