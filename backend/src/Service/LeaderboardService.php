<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Redis;
use Symfony\Component\Uid\Uuid;

class LeaderboardService
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

    /** @return array{items: array<int, array<string, mixed>>, total: int, currentUser: ?array<string, mixed>} */
    public function ranking(?User $currentUser = null): array
    {
        $total = $this->redis->zCard(self::KEY);
        if ($total === 0) {
            $this->rebuild();
            $total = $this->redis->zCard(self::KEY);
        }
        $scores = $this->redis->zRevRange(self::KEY, 0, 49, true);
        $items = [];
        foreach ($scores as $id => $score) {
            $user = $this->users->find(Uuid::fromString((string) $id));
            if (!$user instanceof User) continue;
            $items[] = [
                'rank' => $this->rankFor($user),
                'id' => $id,
                'username' => $user->getUsername(),
                'avatarUrl' => $user->getAvatarUrl(),
                'points' => (int) $score,
                'level' => $user->getLevel(),
                'trustScore' => $user->getTrustScore(),
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'currentUser' => $currentUser === null ? null : $this->entryFor($currentUser),
        ];
    }

    /** @return array<string, int|string>|null */
    private function entryFor(User $user): ?array
    {
        $rank = $this->rankFor($user);
        // Un membre actif sans attribution récente peut ne pas encore être
        // présent dans Redis : l'ajouter ici garantit son rang hors Top 50.
        if ($rank === null) {
            $this->update($user);
            $rank = $this->rankFor($user);
        }
        if ($rank === null) return null;

        return [
            'rank' => $rank,
            'id' => $user->getId()->toRfc4122(),
            'points' => $user->getPoints(),
        ];
    }

    private function rankFor(User $user): ?int
    {
        $rank = $this->redis->zRevRank(self::KEY, $user->getId()->toRfc4122());
        return $rank === false ? null : $rank + 1;
    }
}
