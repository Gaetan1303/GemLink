<?php

namespace App\Repository;

use App\Entity\Groupe;
use App\Entity\GroupeMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Groupe> */
class GroupeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Groupe::class); }

    /** @return Groupe[] */
    public function page(?string $cursor, int $limit, ?string $search, ?string $visibility, ?User $member): array
    {
        $qb = $this->createQueryBuilder('g')->andWhere('g.status = :active')->setParameter('active', Groupe::STATUS_ACTIVE);
        if ($search !== null && $search !== '') $qb->andWhere('LOWER(g.name) LIKE :search')->setParameter('search', '%'.mb_strtolower($search).'%');
        if ($visibility !== null) $qb->andWhere('g.visibility = :visibility')->setParameter('visibility', $visibility);
        if ($member !== null) $qb->innerJoin(GroupeMember::class, 'gm', 'WITH', 'gm.group = g AND gm.user = :member AND gm.status = :memberStatus')->setParameter('member', $member)->setParameter('memberStatus', GroupeMember::ACTIVE);
        if ($cursor !== null) $qb->andWhere('g.id < :cursor')->setParameter('cursor', $cursor);
        return $qb->orderBy('g.id', 'DESC')->setMaxResults($limit + 1)->getQuery()->getResult();
    }
}
