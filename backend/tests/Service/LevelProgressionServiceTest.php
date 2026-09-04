<?php

namespace App\Tests\Service;

use App\Entity\Niveau;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NiveauRepository;
use App\Service\LevelProgressionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class LevelProgressionServiceTest extends TestCase
{
    public function testItCalculatesTheConfiguredIntervalAndCapsTheLastLevel(): void
    {
        $service = $this->service();

        self::assertSame([
            'level' => 2, 'name' => 'Initié', 'minPoints' => 100,
            'nextLevel' => 3, 'nextLevelName' => 'Connaisseur', 'nextLevelMinPoints' => 500,
            'progressPercent' => 25,
        ], $service->calculate(200));
        self::assertSame(100, $service->calculate(10000)['progressPercent']);
    }

    public function testPromotionUpdatesUserAndCreatesInAppNotification(): void
    {
        $levels = $this->levels();
        $repository = $this->createMock(NiveauRepository::class);
        $repository->method('findAllOrdered')->willReturn($levels);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->callback(static fn ($entity) => $entity instanceof Notification && $entity->getType() === Notification::TYPE_LEVEL_UP && $entity->getTargetType() === Notification::TARGET_TYPE_LEVEL));
        $user = new User();
        $user->setPoints(100)->setLevel(1);

        (new LevelProgressionService($repository, $em))->synchronize($user);

        self::assertSame(2, $user->getLevel());
    }

    private function service(): LevelProgressionService
    {
        $repository = $this->createMock(NiveauRepository::class);
        $repository->method('findAllOrdered')->willReturn($this->levels());
        return new LevelProgressionService($repository, $this->createMock(EntityManagerInterface::class));
    }

    /** @return list<Niveau> */
    private function levels(): array
    {
        return [new Niveau(1, 'Novice', 0), new Niveau(2, 'Initié', 100), new Niveau(3, 'Connaisseur', 500), new Niveau(4, 'Expert', 2000), new Niveau(5, 'Maître', 10000)];
    }
}
