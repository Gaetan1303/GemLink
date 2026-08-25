<?php

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Publication;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\ReportRepository;
use App\Service\ModerationService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ModerationControllerTest extends WebTestCase
{
    public function testDashboardIsForbiddenToRegularUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser(), 'api');

        $client->request('GET', '/api/moderation/reports');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDashboardReturnsReasonDetailsCountsAndPublicationHistory(): void
    {
        $client = static::createClient();
        $moderator = $this->makeUser('MODERATOR');
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/post.jpg');
        $first = (new Report($this->makeUser(), $publication))->setReasonType('SPAM');
        $second = (new Report($this->makeUser(), $publication))
            ->setReasonType('HARASSMENT')
            ->setDescription('Message ciblé');
        $audit = new AuditLog(
            $moderator,
            AuditLog::ACTION_REPORT_REJECTED,
            AuditLog::TARGET_TYPE_PUBLICATION,
            $publication->getId(),
            'Contexte insuffisant',
        );

        $reports = $this->createMock(ReportRepository::class);
        $reports->expects($this->once())->method('findForModeration')->with('PENDING')->willReturn([$first, $second]);
        $audits = $this->createMock(AuditLogRepository::class);
        $audits->expects($this->once())->method('findModerationHistoryForPublications')->willReturn([$audit]);
        $client->getContainer()->set(ReportRepository::class, $reports);
        $client->getContainer()->set(AuditLogRepository::class, $audits);
        $client->loginUser($moderator, 'api');

        $client->request('GET', '/api/moderation/reports');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(2, $data['items']);
        $this->assertSame(2, $data['items'][0]['reportCount']);
        $this->assertCount(2, $data['items'][0]['reasonDetails']);
        $this->assertSame('HARASSMENT', $data['items'][0]['reasonDetails'][1]['reasonType']);
        $this->assertSame('REPORT_REJECTED', $data['items'][0]['moderationHistory'][0]['action']);
        $this->assertSame('Contexte insuffisant', $data['items'][0]['moderationHistory'][0]['reason']);
    }

    public function testDecisionDelegatesToServiceWithModeratorReason(): void
    {
        $client = static::createClient();
        $moderator = $this->makeUser('MODERATOR');
        $report = new Report(
            $this->makeUser(),
            new Publication($this->makeUser(), 'https://media.gem-link.org/post.jpg'),
        );

        $reports = $this->createMock(ReportRepository::class);
        $reports->expects($this->once())->method('find')->willReturn($report);
        $audits = $this->createMock(AuditLogRepository::class);
        $audits->expects($this->once())->method('findModerationHistoryForPublications')->willReturn([]);
        $moderation = $this->createMock(ModerationService::class);
        $moderation->expects($this->once())
            ->method('decide')
            ->with($report, $moderator, 'ACCEPTED', 'Spam confirmé')
            ->willReturnCallback(static function (Report $target): void {
                $target->decide('ACCEPTED');
            });
        $client->getContainer()->set(ReportRepository::class, $reports);
        $client->getContainer()->set(AuditLogRepository::class, $audits);
        $client->getContainer()->set(ModerationService::class, $moderation);
        $client->loginUser($moderator, 'api');

        $client->request(
            'POST',
            '/api/moderation/reports/' . $report->getId()->toRfc4122() . '/decision',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['decision' => 'ACCEPTED', 'reason' => 'Spam confirmé']),
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('ACCEPTED', $data['status']);
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
