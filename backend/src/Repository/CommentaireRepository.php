<?php

namespace App\Repository;

use App\Entity\Commentaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Commentaire>
 */
class CommentaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commentaire::class);
    }

    public function findOneActiveById(Uuid $id): ?Commentaire
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * CA-3 : commentaires actifs d'un post, ordre chronologique croissant,
     * pagination cursor-based. Le curseur est l'id (UUIDv7, donc triable
     * chronologiquement) du dernier commentaire vu sur la page précédente ;
     * le tri secondaire sur l'id lève toute ambiguïté en cas d'égalité
     * stricte de created_at.
     *
     * @return Commentaire[]
     */
    public function findActiveByPublicationPaginated(Uuid $publicationId, ?Uuid $cursor, int $limit): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.publication = :publicationId')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('publicationId', $publicationId)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit);

        if ($cursor !== null) {
            // Le commentaire pointé par le curseur peut lui-même être
            // supprimé entre-temps : on va chercher sa date directement en
            // base (find() ignore le filtre deletedAt) plutôt que de risquer
            // un curseur "perdu" qui casserait la pagination.
            $cursorComment = $this->find($cursor);

            if ($cursorComment !== null) {
                $qb->andWhere('(c.createdAt > :cursorCreatedAt) OR (c.createdAt = :cursorCreatedAt AND c.id > :cursorId)')
                    ->setParameter('cursorCreatedAt', $cursorComment->getCreatedAt())
                    ->setParameter('cursorId', $cursor);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function countActiveForPublication(Uuid $publicationId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.publication = :publicationId')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('publicationId', $publicationId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
