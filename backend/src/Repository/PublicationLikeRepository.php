<?php

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\PublicationLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PublicationLike> */
class PublicationLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, PublicationLike::class); }

    public function findOneFor(Publication $publication, User $user): ?PublicationLike
    {
        return $this->findOneBy(['publication' => $publication, 'user' => $user]);
    }

    public function countForPublication(Publication $publication): int
    {
        return (int) $this->count(['publication' => $publication]);
    }
}
