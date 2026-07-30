<?php

namespace App\Repository;

use App\Entity\ParametreSysteme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParametreSysteme>
 */
class ParametreSystemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParametreSysteme::class);
    }

    public function findOneByCle(string $cle): ?ParametreSysteme
    {
        return $this->findOneBy(['cle' => $cle]);
    }
}
