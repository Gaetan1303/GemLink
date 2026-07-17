<?php

namespace App\Repository;

use App\Entity\VitrinePublication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VitrinePublication>
 */
class VitrinePublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VitrinePublication::class);
    }

    public function save(VitrinePublication $item, bool $flush = true): void
    {
        $this->getEntityManager()->persist($item);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VitrinePublication $item, bool $flush = true): void
    {
        $this->getEntityManager()->remove($item);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}