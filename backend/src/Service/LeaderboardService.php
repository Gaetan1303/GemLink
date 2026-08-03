<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Redis;
use Symfony\Component\Uid\Uuid;

final class LeaderboardService
{
    private const KEY = 'leaderboard:global';

    public function __construct(private readonly Redis $redis, private readonly UserRepository $users) {}

    public function update(User $user): void
    {
        if ($user->getStatus() === 'ACTIVE') {
            $this->redis->zAdd(self::KEY, $user->getPoints(), $user->getId()->toRfc4122());
        } else {
            $this->redis->zRem(self::KEY, $user->getId()->toRfc4122());
        }
    }

    public function rebuild(): int
    {
        $this->redis->del(self::KEY);
        $users = $this->users->findActiveLeaderboard();
        foreach ($users as $user) $this->update($user);
        return count($users);
    }

    public function ranking(int $offset = 0, int $limit = 50): array
    {
        if ($this->redis->zCard(self::KEY) === 0) $this->rebuild();
        $scores = $this->redis->zRevRange(self::KEY, $offset, $offset + $limit - 1, true);
        $items = [];
        foreach ($scores as $id => $score) {
            $user = $this->users->find(Uuid::fromString((string) $id));
            if (!$user instanceof User) continue;
            $items[] = [
                'rank' => $offset + count($items) + 1,
                'id' => $id,
                'username' => $user->getUsername(),
                'avatarUrl' => $user->getAvatarUrl(),
                'points' => (int) $score,
                'level' => $user->getLevel(),
                'trustScore' => $user->getTrustScore(),
            ];
        }
        return ['items' => $items, 'total' => $this->redis->zCard(self::KEY)];
    }
}
