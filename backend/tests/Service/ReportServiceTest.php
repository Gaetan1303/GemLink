<?php

namespace App\Tests\Service;

use App\Entity\Publication;
use App\Entity\User;
use App\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ReportServiceTest extends TestCase
{
    public function testCreatesPendingReportWithOptionalDescription(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $service = new ReportService($em);
        $reporter = $this->makeUser();
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/post.jpg');
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $report = $service->create($reporter, $publication, 'SPAM', '  ');

        self::assertSame('SPAM', $report->getReasonType());
        self::assertNull($report->getDescription());
        self::assertSame('PENDING', $report->getStatus());
    }

    private function makeUser(): User
    {
        return (new User())
            ->setUsername('user_' . uniqid())
            ->setEmail(uniqid() . '@example.test')
            ->setPasswordHash('hash')
            ->setRole('USER');
    }
}
