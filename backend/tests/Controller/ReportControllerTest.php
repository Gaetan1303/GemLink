<?php

namespace App\Tests\Controller;

use App\Entity\Publication;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\PublicationRepository;
use App\Repository\ReportRepository;
use App\Service\ReportService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ReportControllerTest extends WebTestCase
{
    public function testReportRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/publications/' . Uuid::v7()->toRfc4122() . '/reports');

        self::assertContains($client->getResponse()->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
    }

    public function testAuthenticatedUserCanReportWithAnAllowedReason(): void
    {
        [$client, $posts, $reports, $service] = $this->clientWithDependencies();
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/post.jpg');
        $reporter = $this->makeUser();
        $posts->method('findOneActiveById')->willReturn($publication);
        $client->loginUser($reporter, 'api');
        $reports->expects($this->once())->method('findOneBy')->willReturn(null);
        $service->expects($this->once())
            ->method('create')
            ->with($reporter, $publication, 'HARASSMENT', 'Propos insultants')
            ->willReturn((new Report($reporter, $publication))->setReasonType('HARASSMENT'));

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/reports',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['reasonType' => 'HARASSMENT', 'description' => 'Propos insultants']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('PENDING', json_decode((string) $client->getResponse()->getContent(), true)['status']);
    }

    public function testReasonIsMandatoryAndMustBeFromThePredefinedList(): void
    {
        [$client, $posts] = $this->clientWithDependencies();
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/post.jpg');
        $posts->method('findOneActiveById')->willReturn($publication);
        $client->loginUser($this->makeUser(), 'api');

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/reports',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['reasonType' => 'OTHER']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDuplicateReportReturnsConflictWithClearMessage(): void
    {
        [$client, $posts, $reports] = $this->clientWithDependencies();
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/post.jpg');
        $reporter = $this->makeUser();
        $posts->method('findOneActiveById')->willReturn($publication);
        $client->loginUser($reporter, 'api');
        $reports->expects($this->once())->method('findOneBy')->willReturn(new \App\Entity\Report($reporter, $publication));

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/reports',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['reasonType' => 'SPAM']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('Cette publication a déjà été signalée.', json_decode((string) $client->getResponse()->getContent(), true)['message']);
    }

    /** @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: PublicationRepository&MockObject, 2: ReportRepository&MockObject, 3: ReportService&MockObject} */
    private function clientWithDependencies(): array
    {
        $client = static::createClient();
        $posts = $this->createMock(PublicationRepository::class);
        $reports = $this->createMock(ReportRepository::class);
        $service = $this->createMock(ReportService::class);
        $client->getContainer()->set(PublicationRepository::class, $posts);
        $client->getContainer()->set(ReportRepository::class, $reports);
        $client->getContainer()->set(ReportService::class, $service);

        return [$client, $posts, $reports, $service];
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
