<?php

namespace App\Service;

use App\Entity\Publication;
use App\Repository\EmbeddingRepository;
use Redis;

final class SimilarPublicationService
{
    private const TTL = 3600;

    public function __construct(
        private readonly EmbeddingRepository $embeddings,
        private readonly Redis $redis,
    ) {}

    /** @return array<int, array{id: string, similarity: float}> */
    public function find(Publication $publication, int $limit = 5): array
    {
        $key = 'publication:similar:' . $publication->getId()->toRfc4122();
        if ($publication->getViewCount() > 10) {
            $cached = $this->redis->get($key);
            if (is_string($cached)) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) return $decoded;
            }
        }

        $results = $this->embeddings->findSimilarPublicationIds(
            $publication->getId()->toRfc4122(),
            min(10, max(1, $limit)),
        );
        if ($publication->getViewCount() > 10) {
            $this->redis->setEx($key, self::TTL, json_encode($results, JSON_THROW_ON_ERROR));
        }
        return $results;
    }
}
