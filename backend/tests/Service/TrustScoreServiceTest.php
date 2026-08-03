<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\TrustScoreService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrustScoreServiceTest extends TestCase
{
    #[DataProvider('scoreCases')]
    public function testRecalculateAppliesReliabilityAndGrowingSeniority(
        int $confirmed,
        int $total,
        int $expected,
    ): void {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn([
            'confirmed' => (string) $confirmed,
            'total' => (string) $total,
        ]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $service = new TrustScoreService($connection, $em);
        $user = $this->makeUser();

        $this->assertSame($expected, $service->recalculate($user));
        $this->assertSame($expected, $user->getTrustScore());
    }

    public static function scoreCases(): iterable
    {
        yield 'aucune validation finalisée' => [0, 0, 0];
        yield 'première validation correcte, ancienneté minimale' => [1, 1, 53];
        yield 'dix validations correctes' => [10, 10, 75];
        yield 'fiabilité de moitié à ancienneté complète' => [10, 20, 50];
        yield 'fiabilité parfaite à ancienneté complète' => [20, 20, 100];
    }

    public function testChangingRoleToAdminDoesNotAlterCalculatedTrustScore(): void
    {
        $user = $this->makeUser()->setTrustScore(42);

        $user->setRole('ADMIN');

        $this->assertSame(42, $user->getTrustScore());
    }

    private function makeUser(): User
    {
        return (new User())
            ->setUsername('gemuser_' . uniqid())
            ->setEmail(uniqid() . '@example.com')
            ->setPasswordHash('hashed');
    }
}
