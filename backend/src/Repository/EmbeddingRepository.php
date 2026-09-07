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
                ORDER BY other.vector_data <=> source.vector_data,
                         other.publication_id ASC
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

    /** Read-only context from community-validated publications and the same CLIP version.
     * @return list<array{name: string, similarity: float}>
     */
    public function findReviewReferences(array $vector, string $modelVersion, string $excludeId, int $limit = 20): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT stone.name, 1 - (e.vector_data <=> CAST(:vector AS vector)) AS similarity
                FROM embedding e
                JOIN publication p ON p.id = e.publication_id
                JOIN ai_model_version model ON model.id = e.version_modele_ia_id
                JOIN publication_pierre match ON match.publication_id = p.id
                JOIN pierre stone ON stone.id = match.pierre_id
                WHERE p.deleted_at IS NULL AND p.status = 'COMMUNITY_VALIDATED'
                  AND p.id <> CAST(:exclude AS uuid) AND model.name = :model AND model.model_type = 'CLIP'
                  AND match.confidence >= 0.85
                  AND 1 - (e.vector_data <=> CAST(:vector AS vector)) BETWEEN 0 AND 1
                ORDER BY e.vector_data <=> CAST(:vector AS vector), p.id, stone.id
                LIMIT :limit
                SQL,
            ['vector' => json_encode($vector, JSON_THROW_ON_ERROR), 'model' => $modelVersion, 'exclude' => $excludeId, 'limit' => max(1, min(20, $limit))],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
        return array_map(static fn (array $row) => ['name' => (string) $row['name'], 'similarity' => (float) $row['similarity']], $rows);
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
