<?php

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use DateTimeImmutable;

/**
 * @extends ServiceEntityRepository<Publication>
 */
class PublicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Publication::class);
    }

    /**
     * US 2.2 — Consultation des posts (liste + détail).
     */
    public function findOneActiveById(Uuid $id): ?Publication
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Feed public (visiteurs inclus, cf. diagramme "Vitrine publique") : posts
     * actifs triés du plus récent au plus ancien, sans filtre sur le statut
     * d'analyse IA (un post PENDING_ANALYSIS reste visible dès sa création, CA-3).
     *
     * @return Publication[]
     */
    public function findActivePaginated(int $page, int $limit): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Keyset pagination: the pair (createdAt, id) makes the next page stable
     * when newer publications are inserted while the visitor is scrolling.
     *
     * @return Publication[] at most $limit + 1 rows (the extra row signals a next page)
     */
    public function findFeed(?DateTimeImmutable $beforeDate, ?Uuid $beforeId, int $limit, ?string $tag, ?string $mineral, ?float $minConfidence, ?User $interestsOf = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.tags', 'tag')
            ->leftJoin('App\\Entity\\PublicationPierre', 'pp', 'WITH', 'pp.publication = p')
            ->leftJoin('pp.pierre', 'mineral')
            ->andWhere('p.deletedAt IS NULL');

        if ($beforeDate !== null && $beforeId !== null) {
            $qb->andWhere('(p.createdAt < :beforeDate OR (p.createdAt = :beforeDate AND p.id < :beforeId))')
                ->setParameter('beforeDate', $beforeDate)->setParameter('beforeId', $beforeId);
        }
        if ($tag !== null) {
            $qb->andWhere('LOWER(tag.name) = :tag')->setParameter('tag', mb_strtolower($tag));
        }
        if ($mineral !== null) {
            $qb->andWhere('LOWER(mineral.name) = :mineral')->setParameter('mineral', mb_strtolower($mineral));
        }
        if ($minConfidence !== null) {
            $qb->andWhere('pp.confidence >= :minConfidence')->setParameter('minConfidence', $minConfidence);
        }
        if ($interestsOf !== null && !$interestsOf->getInterestTags()->isEmpty()) {
            $qb->andWhere('tag IN (:interestTags)')->setParameter('interestTags', $interestsOf->getInterestTags());
        }

        return $qb->distinct()->orderBy('p.createdAt', 'DESC')->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit + 1)->getQuery()->getResult();
    }

    /** @return Publication[] */
    public function findActiveByIds(array $ids): array
    {
        if ($ids === []) return [];
        $posts = $this->createQueryBuilder('p')->andWhere('p.id IN (:ids)')->andWhere('p.deletedAt IS NULL')
            ->setParameter('ids', $ids)->getQuery()->getResult();
        $byId = [];
        foreach ($posts as $post) $byId[$post->getId()->toRfc4122()] = $post;
        return array_values(array_filter(array_map(static fn (string $id) => $byId[$id] ?? null, $ids)));
    }

    /** @return string[] */
    public function findRecentActiveIds(int $limit): array
    {
        return array_map(static fn (Publication $post) => $post->getId()->toRfc4122(), $this->createQueryBuilder('p')
            ->select('p')->andWhere('p.deletedAt IS NULL')->orderBy('p.createdAt', 'DESC')->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)->getQuery()->getResult());
    }

    /** @return Publication[] */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('p')->andWhere('p.user = :user')->andWhere('p.deletedAt IS NULL')
            ->setParameter('user', $user)->orderBy('p.createdAt', 'DESC')->getQuery()->getResult();
    }

    //    /**
    //     * @return Publication[] Returns an array of Publication objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Publication
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
