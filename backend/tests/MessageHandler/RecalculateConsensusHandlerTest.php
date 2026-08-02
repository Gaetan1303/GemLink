<?php



namespace App\Tests\MessageHandler;

use App\Entity\Pierre;
use App\Entity\Publication;
use App\Entity\User;
use App\Message\RecalculateConsensusMessage;
use App\MessageHandler\RecalculateConsensusHandler;
use App\Repository\PierreRepository;
use App\Repository\PublicationPierreRepository;
use App\Repository\PublicationRepository;
use App\Repository\ValidationRepository;
use App\Service\ConsensusCalculatorService;
use App\Service\ConsensusResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RecalculateConsensusHandlerTest extends TestCase
{
    private PublicationRepository&MockObject $publications;
    private PierreRepository&MockObject $pierres;
    private PublicationPierreRepository&MockObject $publicationPierres;
    private ConsensusCalculatorService&MockObject $consensusCalculator;
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private ValidationRepository&MockObject $validations;
    private MessageBusInterface&MockObject $messageBus;
    private RecalculateConsensusHandler $handler;

    protected function setUp(): void
    {
        $this->publications = $this->createMock(PublicationRepository::class);
        $this->pierres = $this->createMock(PierreRepository::class);
        $this->publicationPierres = $this->createMock(PublicationPierreRepository::class);
        $this->consensusCalculator = $this->createMock(ConsensusCalculatorService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->validations = $this->createMock(ValidationRepository::class);
        $this->validations->method('findByPublication')->willReturn([]);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->messageBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->handler = new RecalculateConsensusHandler(
            $this->publications,
            $this->pierres,
            $this->publicationPierres,
            $this->consensusCalculator,
            $this->em,
            $this->logger,
            $this->validations,
            $this->messageBus,
        );
    }

    public function testMissingPublicationIsSkipped(): void
    {
        $this->publications->method('find')->willReturn(null);
        $this->consensusCalculator->expects($this->never())->method('calculate');

        $this->handler->__invoke(new RecalculateConsensusMessage(Uuid::v7()->toRfc4122()));
    }

    public function testConsensusBelowThresholdDoesNotChangeAnything(): void
    {
        $publication = $this->makePublication();
        $this->publications->method('find')->willReturn($publication);
        $this->consensusCalculator->method('calculate')->willReturn(ConsensusResult::none());

        $this->publicationPierres->expects($this->never())->method('upsertMatch');
        $this->em->expects($this->never())->method('flush');

        $this->handler->__invoke(new RecalculateConsensusMessage((string) $publication->getId()));

        $this->assertSame(Publication::STATUS_PENDING_ANALYSIS, $publication->getStatus());
    }

    public function testValidatedConsensusUpsertsMatchAndUpdatesStatus(): void
    {
        $publication = $this->makePublication();
        $pierre = new Pierre('Améthyste');

        $this->publications->method('find')->willReturn($publication);
        $this->pierres->method('find')->willReturn($pierre);
        $this->consensusCalculator->method('calculate')->willReturn(new ConsensusResult($pierre->getId(), 0.82, true));

        $this->publicationPierres->expects($this->once())->method('upsertMatch')->with($publication, $pierre, 0.82);
        $this->em->expects($this->once())->method('flush');

        $this->handler->__invoke(new RecalculateConsensusMessage((string) $publication->getId()));

        $this->assertSame(Publication::STATUS_COMMUNITY_VALIDATED, $publication->getStatus());
    }

    public function testMissingWinningPierreIsLoggedAndSkipped(): void
    {
        $publication = $this->makePublication();
        $this->publications->method('find')->willReturn($publication);
        $this->pierres->method('find')->willReturn(null);
        $this->consensusCalculator->method('calculate')->willReturn(new ConsensusResult(Uuid::v7(), 0.9, true));

        $this->logger->expects($this->once())->method('error');
        $this->publicationPierres->expects($this->never())->method('upsertMatch');

        $this->handler->__invoke(new RecalculateConsensusMessage((string) $publication->getId()));
    }

    private function makePublication(): Publication
    {
        $author = new User();
        $author->setUsername('gemuser_' . uniqid());
        $author->setEmail(uniqid() . '@example.com');
        $author->setPasswordHash('hashed');
        $author->setRole('USER');

        return new Publication($author, 'https://media.gem-link.org/x.jpg');
    }
}
