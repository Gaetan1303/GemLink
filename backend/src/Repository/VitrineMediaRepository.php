<?php

namespace App\Repository;

use App\Entity\VitrineMedia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VitrineMedia>
 */
class VitrineMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VitrineMedia::class);
    }

    public function save(VitrineMedia $media, bool $flush = true): void
    {
        $this->getEntityManager()->persist($media);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VitrineMedia $media, bool $flush = true): void
    {
        $this->getEntityManager()->remove($media);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}