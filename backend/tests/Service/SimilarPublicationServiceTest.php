<?php

namespace App\Tests\Service;

use App\Entity\Publication;
use App\Entity\User;
use App\Repository\EmbeddingRepository;
use App\Service\SimilarPublicationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Redis;

final class SimilarPublicationServiceTest extends TestCase
{
    private EmbeddingRepository&MockObject $embeddings;
    private Redis&MockObject $redis;

    protected function setUp(): void
    {
        $this->embeddings = $this->createMock(EmbeddingRepository::class);
        $this->redis = $this->createMock(Redis::class);
    }

    #[DataProvider('unsupportedStatuses')]
    public function testPendingOrFailedAnalysisReturnsNoResults(string $status): void
    {
        $publication = $this->publication($status);
        $this->embeddings->expects(self::never())->method('findSimilarPublicationIds');
        $this->redis->expects(self::never())->method('get');

        self::assertSame([], $this->service()->find($publication));
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedStatuses(): iterable
    {
        yield 'analysis pending' => [Publication::STATUS_PENDING_ANALYSIS];
        yield 'analysis failed' => [Publication::STATUS_ANALYSIS_FAILED];
    }

    public function testResultsAreLimitedToFiveAndOrderedByTheRepository(): void
    {
        $publication = $this->publication(Publication::STATUS_ANALYZED);
        $matches = $this->similarityMatches(5);
        $this->redis->expects(self::never())->method('get');
        $this->embeddings->expects(self::once())
            ->method('findSimilarPublicationIds')
            ->with($publication->getId()->toRfc4122(), 5)
            ->willReturn($matches);

        self::assertSame($matches, $this->service()->find($publication, 20));
    }

    public function testPopularPostUsesItsOneHourCache(): void
    {
        $publication = $this->popularPublication();
        $cached = $this->similarityMatches(5);
        $this->redis->expects(self::once())->method('get')->willReturn(json_encode($cached, JSON_THROW_ON_ERROR));
        $this->embeddings->expects(self::never())->method('findSimilarPublicationIds');

        self::assertSame(array_slice($cached, 0, 2), $this->service()->find($publication, 2));
    }

    public function testPopularPostCachesTheCanonicalFiveResultsForOneHour(): void
    {
        $publication = $this->popularPublication();
        $matches = $this->similarityMatches(5);
        $this->redis->expects(self::once())->method('get')->willReturn(false);
        $this->embeddings->expects(self::once())
            ->method('findSimilarPublicationIds')
            ->with($publication->getId()->toRfc4122(), 5)
            ->willReturn($matches);
        $this->redis->expects(self::once())
            ->method('setEx')
            ->with(
                'publication:similar:' . $publication->getId()->toRfc4122(),
                SimilarPublicationService::CACHE_TTL,
                json_encode($matches, JSON_THROW_ON_ERROR),
            );

        self::assertSame([$matches[0]], $this->service()->find($publication, 1));
    }

    private function service(): SimilarPublicationService
    {
        return new SimilarPublicationService($this->embeddings, $this->redis);
    }

    private function publication(string $status): Publication
    {
        $user = new User();
        $user->setUsername('similar-test')->setEmail('similar@example.com')->setPasswordHash('hash');

        return (new Publication($user, 'https://media.gem-link.org/specimen.jpg'))->setStatus($status);
    }

    private function popularPublication(): Publication
    {
        $publication = $this->publication(Publication::STATUS_ANALYZED);
        for ($view = 0; $view < 11; ++$view) $publication->incrementViewCount();

        return $publication;
    }

    /** @return array<int, array{id: string, similarity: float}> */
    private function similarityMatches(int $count): array
    {
        $matches = [];
        for ($index = 1; $index <= $count; ++$index) {
            $matches[] = ['id' => sprintf('00000000-0000-7000-8000-%012d', $index), 'similarity' => 1 - ($index / 10)];
        }

        return $matches;
    }
}
