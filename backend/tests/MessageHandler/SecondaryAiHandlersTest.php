<?php

namespace App\Tests\MessageHandler;

use App\Dto\StoneAiReviewResponse;
use App\Entity\{Publication, PublicIdentification, User, Pierre, Embedding};
use App\Message\{AnalyzeMediaMessage, AnalyzePublicIdentificationMessage};
use App\MessageHandler\{AnalyzeMediaMessageHandler, AnalyzePublicIdentificationMessageHandler};
use App\Repository\{PublicationRepository, PublicIdentificationRepository, PierreRepository, PublicationPierreRepository, EmbeddingRepository, VersionModeleIaRepository};
use App\Service\{AiOrchestrationService, PublicIdentificationService, SecondaryAiReviewerInterface};
use App\Service\Media\{AiImageSanitizer, AiMediaReader};
use App\Tests\Support\SecondaryAiFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\MessageBusInterface;

final class SecondaryAiHandlersTest extends TestCase
{
    use SecondaryAiFixtures;

    public static function outcomes(): iterable { yield ['unknown']; yield ['candidate']; yield ['timeout']; }

    #[DataProvider('outcomes')]
    public function testPublicationUsesPrimaryThenReviewerAndPreservesPersistenceOwnership(string $decision): void
    {
        $user = new User(); $publication = new Publication($user, 'https://8.8.8.8/x.png');
        $publications = $this->createStub(PublicationRepository::class); $publications->method('find')->willReturn($publication);
        $stone = new Pierre('Quartz');
        $pierres = $this->createMock(PierreRepository::class);
        $pierres->expects(self::once())->method('findOneByNameIgnoreCase')->willReturn($stone);
        $embeddings = $this->createStub(EmbeddingRepository::class); $embeddings->method('findReviewReferences')->willReturn([]);
        $reviewer = $this->reviewer($decision, $stone);
        $orchestration = new AiOrchestrationService($this->createStub(MessageBusInterface::class), $reviewer, $this->configuration(), $pierres, $embeddings);
        $matches = $this->createMock(PublicationPierreRepository::class);
        if ($decision === 'candidate') $matches->expects(self::once())->method('upsertMatch')->with($publication, $stone, .7);
        else $matches->expects(self::never())->method('upsertMatch');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(function ($entity) { self::assertNotInstanceOf(Pierre::class, $entity); });
        $em->expects(self::exactly($decision === 'candidate' ? 2 : 1))->method('flush');
        $http = $this->primaryHttp();
        $handler = new AnalyzeMediaMessageHandler($publications, $pierres, $this->createStub(VersionModeleIaRepository::class), $matches, $embeddings, $em,
            $http, 'http://fastapi.test', 'internal-test-key', 'r2', '/tmp', 'http://localhost/uploads', aiOrchestration: $orchestration, mediaReader: $this->reader($http));
        $handler(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));
        self::assertSame(Publication::STATUS_ANALYZED, $publication->getStatus());
        self::assertSame(2, $http->getRequestsCount());
        if ($decision !== 'candidate') self::assertCount(0, $publication->getTags());
    }

    #[DataProvider('outcomes')]
    public function testPublicIdentificationCompletesAndReleasesLockOnSecondaryFailure(string $decision): void
    {
        $identification = new PublicIdentification('server-ip-hash', 'https://8.8.8.8/x.png', 'image/jpeg');
        $identifications = $this->createStub(PublicIdentificationRepository::class); $identifications->method('find')->willReturn($identification);
        $stone = new Pierre('Quartz');
        $pierres = $this->createStub(PierreRepository::class); $pierres->method('findOneByNameIgnoreCase')->willReturn($stone);
        $embeddings = $this->createStub(EmbeddingRepository::class); $embeddings->method('findReviewReferences')->willReturn([]);
        $orchestration = new AiOrchestrationService($this->createStub(MessageBusInterface::class), $this->reviewer($decision, $stone), $this->configuration(), $pierres, $embeddings);
        $service = $this->createMock(PublicIdentificationService::class); $service->expects(self::once())->method('releaseActiveLock')->with($identification);
        $em = $this->createMock(EntityManagerInterface::class); $em->expects(self::once())->method('flush'); $em->expects(self::never())->method('persist');
        $http = $this->primaryHttp();
        $handler = new AnalyzePublicIdentificationMessageHandler($identifications, $service, $em, $http, 'http://fastapi.test', 'internal-test-key', 'r2', '/tmp', 'http://localhost/uploads', $orchestration, $this->reader($http));
        $handler(new AnalyzePublicIdentificationMessage($identification->getId()->toRfc4122()));
        self::assertSame(PublicIdentification::STATUS_ANALYZED, $identification->getStatus());
        self::assertSame($decision === 'candidate' ? 'Quartz' : 'UNKNOWN', $identification->getResult()['nom']);
        self::assertSame($decision === 'candidate' ? 'candidate' : 'unknown', $identification->getResult()['decision']);
        self::assertSame(2, $http->getRequestsCount());
    }

    private function reviewer(string $decision, Pierre $stone): SecondaryAiReviewerInterface
    {
        $reviewer = $this->createMock(SecondaryAiReviewerInterface::class);
        $reviewer->expects(self::once())->method('review')->willReturnCallback(function ($request) use ($decision, $stone) {
            self::assertSame(.7, $request->modelConfidence);
            self::assertSame('image/png', $request->mimeType);
            if ($decision === 'timeout') throw new \App\Exception\CloudflareAiException('timeout');
            return StoneAiReviewResponse::fromArray($this->verdict(['decision' => $decision, 'stoneId' => $decision === 'candidate' ? $stone->getId()->toRfc4122() : null]), $request, '@cf/meta/test-vision');
        });
        return $reviewer;
    }

    private function primaryHttp(): MockHttpClient
    {
        return new MockHttpClient(function ($method, $url, $options) {
            if ($method === 'GET') return new MockResponse($this->image());
            self::assertSame('http://fastapi.test/analyze', $url);
            self::assertContains('X-Internal-Key: internal-test-key', $options['headers']);
            $body = '';
            if (is_callable($options['body'])) { while (($chunk = $options['body'](8192)) !== '') $body .= $chunk; }
            else $body = is_string($options['body']) ? $options['body'] : implode('', iterator_to_array($options['body']));
            self::assertStringContainsString('Content-Type: image/png', $body);
            return new MockResponse(json_encode($this->primaryData()));
        });
    }

    private function reader(MockHttpClient $http): AiMediaReader
    {
        return new AiMediaReader($http, new AiImageSanitizer(), 'r2', '/tmp', 'http://localhost/uploads', 'https://8.8.8.8');
    }
}
