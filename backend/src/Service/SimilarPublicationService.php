<?php

namespace App\Service;

use App\Entity\Publication;
use App\Repository\EmbeddingRepository;
use Redis;

class SimilarPublicationService
{
    public const CACHE_TTL = 3600;
    public const MAX_RESULTS = 5;

    public function __construct(
        private readonly EmbeddingRepository $embeddings,
        private readonly Redis $redis,
    ) {}

    /** @return array<int, array{id: string, similarity: float}> */
    public function find(Publication $publication, int $limit = 5): array
    {
        if (!$this->isEligible($publication)) {
            return [];
        }

        $limit = min(self::MAX_RESULTS, max(1, $limit));
        $key = 'publication:similar:' . $publication->getId()->toRfc4122();
        if ($publication->getViewCount() > 10) {
            $cached = $this->redis->get($key);
            if (is_string($cached)) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) return array_slice($decoded, 0, $limit);
            }
        }

        $results = $this->embeddings->findSimilarPublicationIds(
            $publication->getId()->toRfc4122(),
            $publication->getViewCount() > 10 ? self::MAX_RESULTS : $limit,
        );
        if ($publication->getViewCount() > 10) {
            $this->redis->setEx($key, self::CACHE_TTL, json_encode($results, JSON_THROW_ON_ERROR));
        }

        return array_slice($results, 0, $limit);
    }

    /** Pre-calculates the canonical five results when a post becomes popular. */
    public function warmCache(Publication $publication): void
    {
        if ($publication->getViewCount() > 10) {
            $this->find($publication, self::MAX_RESULTS);
        }
    }

    private function isEligible(Publication $publication): bool
    {
        return in_array($publication->getStatus(), [
            Publication::STATUS_ANALYZED,
            Publication::STATUS_COMMUNITY_VALIDATED,
        ], true);
    }
}
