<?php

namespace App\Tests\Controller;

use App\Entity\PublicIdentification;
use App\Repository\PublicIdentificationRepository;
use App\Service\PublicIdentificationService;
use App\Tests\Support\SecondaryAiFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PublicIdentificationCloudflareBoundaryTest extends WebTestCase
{
    use SecondaryAiFixtures;

    public function testBrowserCandidatesAndForwardedSpoofDoNotEnterPipeline(): void
    {
        $client = self::createClient();
        $service = $this->createMock(PublicIdentificationService::class);
        $service->expects(self::once())->method('submit')->with(hash('sha256', '198.51.100.12'), self::callback(fn ($file) => $file instanceof UploadedFile))
            ->willReturn(new PublicIdentification(hash('sha256', '198.51.100.12'), 'http://localhost/uploads/x.png', 'image/png'));
        self::getContainer()->set(PublicIdentificationService::class, $service);
        self::getContainer()->set(PublicIdentificationRepository::class, $this->createStub(PublicIdentificationRepository::class));
        $path = tempnam(sys_get_temp_dir(), 'gemlink-boundary-'); file_put_contents($path, $this->image());
        try {
            $client->request('POST', '/api/public/identifications', parameters: [
                'candidates' => [['stoneId' => 'attacker', 'score' => 1]], 'modelConfidence' => 1, 'nearestReferences' => [['name' => 'attacker']],
                'imageType' => 'url', 'image' => 'http://169.254.169.254/',
            ], files: ['image' => new UploadedFile($path, 'photo.png', 'image/png', null, true)], server: [
                'REMOTE_ADDR' => '198.51.100.12', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
            ]);
            self::assertResponseStatusCodeSame(202);
        } finally { if (file_exists($path)) unlink($path); }
    }

    public function testJsonCandidatesWithoutUploadAreRejected(): void
    {
        $client = self::createClient();
        $service = $this->createMock(PublicIdentificationService::class); $service->expects(self::never())->method('submit');
        self::getContainer()->set(PublicIdentificationService::class, $service);
        self::getContainer()->set(PublicIdentificationRepository::class, $this->createStub(PublicIdentificationRepository::class));
        $client->jsonRequest('POST', '/api/public/identifications', ['candidates' => [['stoneId' => 'attacker', 'score' => 1]], 'image' => 'http://localhost']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testExistingPublicLimiterIsAppliedBeforeSubmission(): void
    {
        $client = self::createClient();
        $service = $this->createMock(PublicIdentificationService::class); $service->expects(self::never())->method('submit');
        self::getContainer()->set(PublicIdentificationService::class, $service);
        self::getContainer()->set(PublicIdentificationRepository::class, $this->createStub(PublicIdentificationRepository::class));
        $limiter = self::getContainer()->get('limiter.public_identification')->create('198.51.100.13');
        self::assertTrue($limiter->consume(2)->isAccepted());
        $client->request('POST', '/api/public/identifications', server: ['REMOTE_ADDR' => '198.51.100.13']);
        self::assertResponseStatusCodeSame(429);
    }
}
