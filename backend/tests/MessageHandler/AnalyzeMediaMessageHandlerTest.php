<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Publication;
use App\Entity\User;
use App\Message\AnalyzeMediaMessage;
use App\MessageHandler\AnalyzeMediaMessageHandler;
use App\Repository\PublicationRepository;
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

    protected function setUp(): void
    {
        $this->publications = $this->createMock(PublicationRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    public function testDeletedPublicationIsSkipped(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');
        $publication->setDeletedAt(new \DateTimeImmutable());

        $this->publications->method('find')->willReturn($publication);
        $this->em->expects($this->never())->method('flush');

        $handler = new AnalyzeMediaMessageHandler(
            $this->publications,
            $this->em,
            new MockHttpClient(),
            'http://ai-service.test',
        );

        $handler(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));
    }

    public function testSuccessfulAnalysisMarksPublicationAnalyzed(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $this->publications->method('find')->willReturn($publication);
        $this->em->expects($this->once())->method('flush');

        $httpClient = new MockHttpClient(new MockResponse('{}', ['http_code' => 200]));

        $handler = new AnalyzeMediaMessageHandler($this->publications, $this->em, $httpClient, 'http://ai-service.test');
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

        $handler = new AnalyzeMediaMessageHandler($this->publications, $this->em, $httpClient, 'http://ai-service.test');

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

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole('USER');

        return $user;
    }
}
