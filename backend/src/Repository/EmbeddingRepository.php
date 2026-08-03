<?php

namespace App\Repository;

use App\Entity\Embedding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Embedding>
 */
class EmbeddingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Embedding::class);
    }

    /** @return array<int, array{id: string, similarity: float}> */
    public function findSimilarPublicationIds(string $publicationId, int $limit = 5): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT other.publication_id::text AS id,
                       1 - (other.vector_data <=> source.vector_data) AS similarity
                FROM embedding source
                JOIN embedding other ON other.publication_id <> source.publication_id
                JOIN publication publication ON publication.id = other.publication_id
                WHERE source.publication_id = :publicationId
                  AND publication.deleted_at IS NULL
                  AND publication.status IN ('ANALYZED', 'COMMUNITY_VALIDATED')
                ORDER BY other.vector_data <=> source.vector_data
                LIMIT :limit
                SQL,
            ['publicationId' => $publicationId, 'limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'similarity' => round((float) $row['similarity'], 4),
        ], $rows);
    }

    //    /**
    //     * @return Embedding[] Returns an array of Embedding objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Embedding
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
