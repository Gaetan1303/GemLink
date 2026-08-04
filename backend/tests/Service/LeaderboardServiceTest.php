<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\LeaderboardService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Redis;

final class LeaderboardServiceTest extends TestCase
{
    private Redis&MockObject $redis;
    private UserRepository&MockObject $users;
    private LeaderboardService $service;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(Redis::class);
        $this->users = $this->createMock(UserRepository::class);
        $this->service = new LeaderboardService($this->redis, $this->users);
    }

    public function testRankingAlwaysReadsTheTopFiftyAndIncludesAuthenticatedUsersRankOutsideIt(): void
    {
        $first = $this->user('expert', 250);
        $second = $this->user('amateur', 200);
        $current = $this->user('outside_top_fifty', 12);
        $scores = [
            $first->getId()->toRfc4122() => 250,
            $second->getId()->toRfc4122() => 200,
        ];

        $this->redis->expects($this->once())->method('zCard')->willReturn(73);
        $this->redis->expects($this->once())->method('zRevRange')->with('leaderboard:global', 0, 49, true)->willReturn($scores);
        $this->redis->method('zRevRank')->willReturnCallback(static function (string $key, string $id) use ($first, $second, $current): int|false {
            return match ($id) {
                $first->getId()->toRfc4122() => 0,
                $second->getId()->toRfc4122() => 1,
                $current->getId()->toRfc4122() => 66,
                default => false,
            };
        });
        $this->users->method('find')->willReturnCallback(static fn ($id) => match ($id->toRfc4122()) {
            $first->getId()->toRfc4122() => $first,
            $second->getId()->toRfc4122() => $second,
            default => null,
        });

        $ranking = $this->service->ranking($current);

        self::assertSame([1, 2], array_column($ranking['items'], 'rank'));
        self::assertSame(73, $ranking['total']);
        self::assertSame(['rank' => 67, 'id' => $current->getId()->toRfc4122(), 'points' => 12], $ranking['currentUser']);
    }

    public function testUpdateWritesActiveUsersToTheSortedSet(): void
    {
        $user = $this->user('active', 45);
        $this->redis->expects($this->once())->method('zAdd')->with('leaderboard:global', 45, $user->getId()->toRfc4122());

        $this->service->update($user);
    }

    private function user(string $username, int $points): User
    {
        return (new User())
            ->setUsername($username . '_' . uniqid())
            ->setEmail(uniqid() . '@example.test')
            ->setPasswordHash('hash')
            ->setStatus('ACTIVE')
            ->setPoints($points);
    }
}
