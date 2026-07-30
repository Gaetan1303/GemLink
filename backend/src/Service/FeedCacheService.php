<?php

namespace App\Service;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use Redis;

/** Redis-backed hot global-feed index; DB remains the source of truth. */
final class FeedCacheService
{
    private const LIST = 'feed:global:recent';
    private const STALE_LIST = 'feed:global:recent:stale';
    private const LOCK = 'feed:global:rebuild-lock';
    private const TTL = 300;
    private const STALE_TTL = 600;
    private const CAPACITY = 200;

    public function __construct(private readonly Redis $redis, private readonly PublicationRepository $publications) {}

    /** @return string[] */
    public function recentIds(): array
    {
        $ids = $this->redis->lRange(self::LIST, 0, self::CAPACITY - 1);
        if ($ids !== []) return $ids;

        // A short mutex prevents a cold-cache stampede. A concurrent request
        // receives the previous list while the winner reconstructs the index.
        if ($this->redis->set(self::LOCK, '1', ['nx', 'ex' => 10])) {
            try { $this->rebuild(); } finally { $this->redis->del(self::LOCK); }
            return $this->redis->lRange(self::LIST, 0, self::CAPACITY - 1);
        }
        return $this->redis->lRange(self::STALE_LIST, 0, self::CAPACITY - 1);
    }

    public function prepend(Publication $post): void
    {
        $id = $post->getId()->toRfc4122();
        $this->redis->lRem(self::LIST, $id, 0);
        $this->redis->lPush(self::LIST, $id);
        $this->redis->lTrim(self::LIST, 0, self::CAPACITY - 1);
        $this->redis->expire(self::LIST, self::TTL);
        $this->redis->lRem(self::STALE_LIST, $id, 0);
        $this->redis->lPush(self::STALE_LIST, $id);
        $this->redis->lTrim(self::STALE_LIST, 0, self::CAPACITY - 1);
        $this->redis->expire(self::STALE_LIST, self::STALE_TTL);
    }

    private function rebuild(): void
    {
        $ids = $this->publications->findRecentActiveIds(self::CAPACITY);
        $this->redis->del(self::LIST, self::STALE_LIST);
        if ($ids !== []) {
            $this->redis->rPush(self::LIST, ...$ids);
            $this->redis->rPush(self::STALE_LIST, ...$ids);
        }
        $this->redis->expire(self::LIST, self::TTL);
        $this->redis->expire(self::STALE_LIST, self::STALE_TTL);
    }
}
