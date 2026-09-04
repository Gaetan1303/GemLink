<?php

namespace App\Tests\Service;

use App\Entity\Badge;
use App\Entity\Notification;
use App\Entity\Pierre;
use App\Entity\User;
use App\Repository\BadgeRepository;
use App\Repository\PublicationPierreRepository;
use App\Repository\PublicationRepository;
use App\Service\BadgeAwardService;
use App\Service\MineralIdentificationBadgePrototype;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BadgeAwardServiceTest extends TestCase
{
    public function testNewMineralCreatesPrototypeBadgeAwardsItAndNotifiesOnce(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $badges = $this->createMock(BadgeRepository::class);
        $badges->method('findMineralIdentificationBadge')->willReturn(null);
        $badges->method('findAutomaticBadges')->willReturn([]);
        $persisted = [];
        $em->expects($this->exactly(2))->method('persist')->willReturnCallback(static function ($entity) use (&$persisted): void { $persisted[] = $entity; });
        $service = new BadgeAwardService($em, $badges, $this->createMock(PublicationRepository::class), $this->createMock(PublicationPierreRepository::class), new MineralIdentificationBadgePrototype());
        $user = new User();
        $pierre = new Pierre('Quartz');

        $service->onStoneIdentified($user, $pierre, true);

        self::assertCount(1, $user->getBadges());
        self::assertSame('Découvreur : Quartz', $user->getBadges()->first()->getName());
        $notifications = array_values(array_filter($persisted, static fn ($entity) => $entity instanceof Notification && $entity->getType() === Notification::TYPE_BADGE_AWARDED));
        self::assertCount(1, $notifications);
        self::assertStringContainsString('Découvreur : Quartz', $notifications[0]->getContent());
    }

    public function testAlreadyOwnedBadgeIsIgnoredSilently(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $badge = (new Badge('Premier post'))->setCondition(Badge::CONDITION_STONE_IDENTIFICATION_COUNT, 1);
        $badges = $this->createMock(BadgeRepository::class);
        $badges->method('findAutomaticBadges')->willReturn([$badge]);
        $identifications = $this->createMock(PublicationPierreRepository::class);
        $identifications->method('countForUser')->willReturn(1);
        $em->expects($this->never())->method('persist');
        $service = new BadgeAwardService($em, $badges, $this->createMock(PublicationRepository::class), $identifications, new MineralIdentificationBadgePrototype());
        $user = (new User())->addBadge($badge);

        $service->onStoneIdentified($user, new Pierre('Quartz'), false);

        self::assertCount(1, $user->getBadges());
    }
}
