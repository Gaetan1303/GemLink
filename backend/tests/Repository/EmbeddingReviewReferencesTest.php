<?php

namespace App\Tests\Repository;

use App\Repository\EmbeddingRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/** Temporary tables shadow real names only within this connection; rollback always. */
final class EmbeddingReviewReferencesTest extends KernelTestCase
{
    public function testPgvectorContextFiltersAndOrdering(): void
    {
        $url = getenv('GEMLINK_PGVECTOR_TEST_URL');
        if (!$url) self::markTestSkipped('Set GEMLINK_PGVECTOR_TEST_URL to run the PostgreSQL/pgvector integration check.');
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        /** @var Connection $db */
        $db = $em->getConnection();
        // Configure only this test kernel connection using the explicitly supplied test database.
        $db->close();
        $params = (new \Doctrine\DBAL\Tools\DsnParser(['postgresql' => 'pdo_pgsql']))->parse($url);
        $db = DriverManager::getConnection($params);
        $registry = $this->createStub(\Doctrine\Persistence\ManagerRegistry::class);
        $testEm = $this->createStub(\Doctrine\ORM\EntityManagerInterface::class);
        $testEm->method('getConnection')->willReturn($db);
        $testEm->method('getClassMetadata')->willReturn($em->getClassMetadata(\App\Entity\Embedding::class));
        $registry->method('getManagerForClass')->willReturn($testEm);
        $repository = new EmbeddingRepository($registry);
        $db->beginTransaction();
        try {
            $db->executeStatement('CREATE EXTENSION IF NOT EXISTS vector');
            $db->executeStatement('CREATE TEMP TABLE publication (id uuid, status text, deleted_at timestamp) ON COMMIT DROP');
            $db->executeStatement('CREATE TEMP TABLE pierre (id uuid, name text) ON COMMIT DROP');
            $db->executeStatement('CREATE TEMP TABLE ai_model_version (id uuid, name text, model_type text) ON COMMIT DROP');
            $db->executeStatement('CREATE TEMP TABLE embedding (publication_id uuid, version_modele_ia_id uuid, vector_data vector(512)) ON COMMIT DROP');
            $db->executeStatement('CREATE TEMP TABLE publication_pierre (publication_id uuid, pierre_id uuid, confidence float) ON COMMIT DROP');
            $model = Uuid::v7()->toRfc4122(); $stone = Uuid::v7()->toRfc4122(); $excluded = Uuid::v7()->toRfc4122();
            $vector = array_fill(0, 512, .1);
            $db->insert('ai_model_version', ['id' => $model, 'name' => 'clip-test', 'model_type' => 'CLIP']);
            $db->insert('pierre', ['id' => $stone, 'name' => 'Quartz']);
            foreach ([['COMMUNITY_VALIDATED', null, .9, null], ['ANALYZED', null, .99, null], ['COMMUNITY_VALIDATED', '2026-01-01', .9, null], ['COMMUNITY_VALIDATED', null, .5, null], ['COMMUNITY_VALIDATED', null, .9, $excluded]] as [$status, $deleted, $score, $id]) {
                $id ??= Uuid::v7()->toRfc4122();
                $db->insert('publication', ['id' => $id, 'status' => $status, 'deleted_at' => $deleted]);
                $db->insert('embedding', ['publication_id' => $id, 'version_modele_ia_id' => $model, 'vector_data' => json_encode($vector)]);
                $db->insert('publication_pierre', ['publication_id' => $id, 'pierre_id' => $stone, 'confidence' => $score]);
            }
            $rows = $repository->findReviewReferences($vector, 'clip-test', $excluded);
            self::assertCount(1, $rows); self::assertSame('Quartz', $rows[0]['name']); self::assertEqualsWithDelta(1, $rows[0]['similarity'], .00001);
            self::assertSame([], $repository->findReviewReferences($vector, 'other-model', $excluded));
        } finally { $db->rollBack(); $db->close(); }
    }
}
