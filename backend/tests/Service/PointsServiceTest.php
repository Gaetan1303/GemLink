<?php

namespace App\Tests\Service;

use App\Entity\PointTransaction;
use App\Entity\User;
use App\Repository\PointTransactionRepository;
use App\Service\AdminSettingsProvider;
use App\Service\LevelProgressionService;
use App\Service\LeaderboardService;
use App\Service\PointsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PointsServiceTest extends TestCase
{
    public function testAwardCreatesLedgerEntryAndUpdatesBalance(): void
    {
        $user = $this->user();
        $em = $this->createMock(EntityManagerInterface::class);
        $transactions = $this->createMock(PointTransactionRepository::class);
        $settings = $this->createMock(AdminSettingsProvider::class);
        $transactions->method('hasSource')->willReturn(false);
        $settings->expects($this->once())->method('getPointsForAction')->with(PointsService::ACTION_POST_CREATED)->willReturn(10);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(PointTransaction::class));
        $em->expects($this->once())->method('flush');

        (new PointsService($em, $transactions, $settings))->award(
            $user,
            PointsService::ACTION_POST_CREATED,
            Uuid::v7()->toRfc4122(),
        );

        self::assertSame(10, $user->getPoints());
    }

    public function testDuplicateMessageDoesNotCreditTwice(): void
    {
        $user = $this->user();
        $em = $this->createMock(EntityManagerInterface::class);
        $transactions = $this->createMock(PointTransactionRepository::class);
        $settings = $this->createMock(AdminSettingsProvider::class);
        $transactions->method('hasSource')->willReturn(true);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');
        $settings->expects($this->never())->method('getPointsForAction');

        (new PointsService($em, $transactions, $settings))->award(
            $user,
            PointsService::ACTION_LIKE_RECEIVED,
            Uuid::v7()->toRfc4122(),
        );

        self::assertSame(0, $user->getPoints());
    }

    public function testAwardSynchronizesLevelFromThePreviousBalance(): void
    {
        $user = $this->user()->setPoints(95);
        $em = $this->createMock(EntityManagerInterface::class);
        $transactions = $this->createMock(PointTransactionRepository::class);
        $settings = $this->createMock(AdminSettingsProvider::class);
        $progression = $this->createMock(LevelProgressionService::class);
        $transactions->method('hasSource')->willReturn(false);
        $settings->method('getPointsForAction')->willReturn(10);
        $progression->expects($this->once())->method('synchronize')->with($user, 95);
        $em->expects($this->once())->method('flush');

        (new PointsService($em, $transactions, $settings, $progression))->award(
            $user,
            PointsService::ACTION_POST_CREATED,
            Uuid::v7()->toRfc4122(),
        );

        self::assertSame(105, $user->getPoints());
    }

    public function testAwardUpdatesTheRedisLeaderboardAfterPersistingTheNewBalance(): void
    {
        $user = $this->user();
        $em = $this->createMock(EntityManagerInterface::class);
        $transactions = $this->createMock(PointTransactionRepository::class);
        $settings = $this->createMock(AdminSettingsProvider::class);
        $leaderboard = $this->createMock(LeaderboardService::class);
        $transactions->method('hasSource')->willReturn(false);
        $settings->method('getPointsForAction')->willReturn(10);
        $em->expects($this->once())->method('flush');
        $leaderboard->expects($this->once())->method('update')->with($user);

        (new PointsService($em, $transactions, $settings, null, $leaderboard))->award(
            $user,
            PointsService::ACTION_POST_CREATED,
            Uuid::v7()->toRfc4122(),
        );

        self::assertSame(10, $user->getPoints());
    }

    private function user(): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');

        return $user;
    }
}
