<?php

namespace App\Tests\Service;

use App\Entity\AuditLog;
use App\Entity\Notification;
use App\Entity\Publication;
use App\Entity\Report;
use App\Entity\User;
use App\Service\ModerationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ModerationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ModerationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new ModerationService($this->em);
    }

    public function testAcceptSoftDeletesPostNotifiesAuthorAndWritesCompleteAuditLog(): void
    {
        $author = $this->makeUser();
        $moderator = $this->makeUser('MODERATOR');
        $publication = new Publication($author, 'https://media.gem-link.org/post.jpg');
        $report = (new Report($this->makeUser(), $publication))
            ->setReasonType('SPAM')
            ->setDescription('Publicité répétitive');
        $persisted = [];
        $this->em->expects($this->exactly(2))->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $this->em->expects($this->once())->method('flush');

        $this->service->decide($report, $moderator, 'ACCEPTED', 'Spam commercial confirmé');

        $this->assertSame('ACCEPTED', $report->getStatus());
        $this->assertTrue($publication->isDeleted());

        $notifications = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof Notification));
        $this->assertCount(1, $notifications);
        $this->assertSame(Notification::TYPE_POST_REMOVED_BY_MODERATION, $notifications[0]->getType());
        $this->assertSame($author, $notifications[0]->getUser());
        $this->assertSame($moderator, $notifications[0]->getActor());
        $this->assertTrue($publication->getId()->equals($notifications[0]->getTargetId()));

        $audits = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof AuditLog));
        $this->assertCount(1, $audits);
        $this->assertSame($moderator, $audits[0]->getUser());
        $this->assertSame(AuditLog::ACTION_REPORT_ACCEPTED, $audits[0]->getAction());
        $this->assertSame(AuditLog::TARGET_TYPE_PUBLICATION, $audits[0]->getTargetType());
        $this->assertTrue($publication->getId()->equals($audits[0]->getTargetId()));
        $this->assertSame('Spam commercial confirmé', $audits[0]->getReason());
    }

    public function testRejectRestoresAnAutomaticallyHiddenPostAndUsesReportReasonAsFallback(): void
    {
        $publication = (new Publication($this->makeUser(), 'https://media.gem-link.org/post.jpg'))
            ->setStatus(Publication::STATUS_AUTO_HIDDEN);
        $report = (new Report($this->makeUser(), $publication))
            ->setReasonType('WRONG_IDENTIFICATION')
            ->setDescription('Quartz au lieu d’améthyste');
        $persisted = [];
        $this->em->expects($this->once())->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $this->em->expects($this->once())->method('flush');

        $this->service->decide($report, $this->makeUser('MODERATOR'), 'REJECTED');

        $this->assertSame('REJECTED', $report->getStatus());
        $this->assertSame(Publication::STATUS_PUBLISHED, $publication->getStatus());
        $this->assertFalse($publication->isDeleted());
        $this->assertCount(0, array_filter($persisted, static fn (object $entity): bool => $entity instanceof Notification));

        $audits = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof AuditLog));
        $this->assertSame(AuditLog::ACTION_REPORT_REJECTED, $audits[0]->getAction());
        $this->assertSame('WRONG_IDENTIFICATION: Quartz au lieu d’améthyste', $audits[0]->getReason());
    }

    public function testAlreadyDecidedReportCannotBeProcessedTwice(): void
    {
        $report = new Report(
            $this->makeUser(),
            new Publication($this->makeUser(), 'https://media.gem-link.org/post.jpg'),
        );
        $report->decide('REJECTED');
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->expectException(\LogicException::class);
        $this->service->decide($report, $this->makeUser('MODERATOR'), 'ACCEPTED');
    }

    private function makeUser(string $role = 'USER'): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole($role);

        return $user;
    }
}
