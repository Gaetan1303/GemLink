<?php

namespace App\Repository;

use App\Entity\Pierre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pierre>
 */
class PierreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pierre::class);
    }

    /**
     * Le classifieur ViT renvoie des labels en minuscules (`class_name.lower()`
     * côté FastAPI) ; la comparaison insensible à la casse évite de créer des
     * doublons ("Améthyste" vs "amethyste") au fil des analyses.
     */
    public function findOneByNameIgnoreCase(string $name): ?Pierre
    {
        return $this->createQueryBuilder('p')
            ->andWhere('LOWER(p.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}