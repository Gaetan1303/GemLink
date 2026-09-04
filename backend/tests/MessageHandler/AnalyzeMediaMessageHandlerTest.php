<?php



namespace App\Tests\MessageHandler;

use App\Entity\Publication;
use App\Entity\User;
use App\Message\AnalyzeMediaMessage;
use App\Repository\EmbeddingRepository;
use App\Repository\PierreRepository;
use App\Repository\PublicationPierreRepository;
use App\MessageHandler\AnalyzeMediaMessageHandler;
use App\Repository\PublicationRepository;
use App\Repository\TagRepository;
use App\Repository\VersionModeleIaRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * US 2.1 CA-3 : le handler doit relancer les échecs (au lieu de les avaler)
 * pour laisser Symfony Messenger appliquer le retry exponentiel — voir
 * App\EventListener\AnalyzeMediaFailureListener pour la bascule finale.
 */
final class AnalyzeMediaMessageHandlerTest extends TestCase
{
    private PublicationRepository&MockObject $publications;
    private EntityManagerInterface&MockObject $em;
    private PierreRepository&MockObject $pierres;
    private VersionModeleIaRepository&MockObject $modelVersions;
    private PublicationPierreRepository&MockObject $publicationPierres;
    private EmbeddingRepository&MockObject $embeddings;

    protected function setUp(): void
    {
        $this->publications = $this->createMock(PublicationRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->pierres = $this->createMock(PierreRepository::class);
        $this->modelVersions = $this->createMock(VersionModeleIaRepository::class);
        $this->publicationPierres = $this->createMock(PublicationPierreRepository::class);
        $this->embeddings = $this->createMock(EmbeddingRepository::class);
    }

    public function testDeletedPublicationIsSkipped(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');
        $publication->setDeletedAt(new \DateTimeImmutable());

        $this->publications->method('find')->willReturn($publication);
        $this->em->expects($this->never())->method('flush');

        $handler = $this->makeHandler(new MockHttpClient());

        $handler(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));
    }

    public function testSuccessfulAnalysisMarksPublicationAnalyzed(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $this->publications->method('find')->willReturn($publication);
        $this->em->expects($this->exactly(2))->method('flush');

        $this->pierres->method('findOneByNameIgnoreCase')->willReturn(new \App\Entity\Pierre('Améthyste'));
        $this->modelVersions->method('findActiveByType')->willReturn(new \App\Entity\VersionModeleIa('clip', 'CLIP', 'ACTIVE'));
        $this->embeddings->method('findOneBy')->willReturn(null);
        $this->publicationPierres->expects($this->once())->method('upsertMatch');

        $httpClient = new MockHttpClient([
            new MockResponse('image-content', ['http_code' => 200, 'response_headers' => ['content-type: image/jpeg']]),
            new MockResponse(json_encode([
                'nom' => 'Améthyste', 'confidence' => 0.9, 'detector_confidence' => 0.9,
                'embedding' => array_fill(0, 512, 0.1),
                'model_version' => ['yolo' => 'yolo-v1', 'vit' => 'vit-v1', 'clip' => 'clip-v1'],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);

        $handler = $this->makeHandler($httpClient);
        $handler(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));

        $this->assertSame(Publication::STATUS_ANALYZED, $publication->getStatus());
    }

    public function testAiServiceErrorIsRethrownForMessengerRetry(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $this->publications->method('find')->willReturn($publication);
        $this->em->expects($this->never())->method('flush');

        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $handler = $this->makeHandler($httpClient);

        try {
            $handler(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));
            $this->fail('Une RuntimeException était attendue.');
        } catch (RuntimeException) {
            // attendu : Messenger doit voir l'exception pour déclencher le retry exponentiel.
        }

        // Le statut ne doit PAS être basculé en ANALYSIS_FAILED ici : c'est le
        // rôle d'AnalyzeMediaFailureListener, uniquement après épuisement des retries.
        $this->assertSame(Publication::STATUS_PENDING_ANALYSIS, $publication->getStatus());
    }

    public function testSuccessfulIdentificationAddsTheDefaultIdentifiedTag(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');
        $this->publications->method('find')->willReturn($publication);
        $this->pierres->method('findOneByNameIgnoreCase')->willReturn(new \App\Entity\Pierre('Quartz'));
        $this->modelVersions->method('findActiveByType')->willReturn(new \App\Entity\VersionModeleIa('clip', 'CLIP', 'ACTIVE'));
        $this->embeddings->method('findOneBy')->willReturn(null);
        $tags = $this->createMock(TagRepository::class);
        $tags->method('findOneByName')->with('Identifiée')->willReturn(null);

        $handler = $this->makeHandler(new MockHttpClient([
            new MockResponse('image-content', ['http_code' => 200, 'response_headers' => ['content-type: image/jpeg']]),
            new MockResponse(json_encode(['nom' => 'Quartz', 'confidence' => 0.9, 'detector_confidence' => 0.9, 'embedding' => array_fill(0, 512, 0.1), 'model_version' => ['yolo' => 'yolo-v1', 'vit' => 'vit-v1', 'clip' => 'clip-v1']], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]), $tags);
        $handler(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));

        self::assertSame(['Identifiée'], array_map(static fn ($tag) => $tag->getName(), $publication->getTags()->toArray()));
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole('USER');

        return $user;
    }

    private function makeHandler(MockHttpClient $httpClient, ?TagRepository $tags = null): AnalyzeMediaMessageHandler
    {
        return new AnalyzeMediaMessageHandler(
            $this->publications,
            $this->pierres,
            $this->modelVersions,
            $this->publicationPierres,
            $this->embeddings,
            $this->em,
            $httpClient,
            'http://ai-service.test',
            'test-internal-key',
            'r2',
            sys_get_temp_dir(),
            'http://localhost/uploads',
            null,
            $tags,
        );
    }
}
