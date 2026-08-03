<?php



namespace App\Tests\Service;

use App\Entity\Pierre;
use App\Entity\Publication;
use App\Entity\PublicationPierre;
use App\Entity\User;
use App\Entity\Validation;
use App\Exception\ValidationPayloadException;
use App\Repository\PublicationPierreRepository;
use App\Repository\ValidationRepository;
use App\Service\AdminSettingsProvider;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ValidationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ValidationRepository&MockObject $validations;
    private PublicationPierreRepository&MockObject $publicationPierres;
    private AdminSettingsProvider&MockObject $adminSettings;
    private MessageBusInterface&MockObject $messageBus;
    private ValidationService $service;
    private Publication $publication;
    private Pierre $pierre;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->validations = $this->createMock(ValidationRepository::class);
        $this->publicationPierres = $this->createMock(PublicationPierreRepository::class);
        $this->adminSettings = $this->createMock(AdminSettingsProvider::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new ValidationService(
            $this->em,
            $this->validations,
            $this->publicationPierres,
            $this->adminSettings,
            $this->messageBus,
        );

        $this->messageBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->publication = new Publication($this->makeUser(50), 'https://media.gem-link.org/x.jpg');
        $this->pierre = new Pierre('Améthyste');
        $this->publicationPierres->method('findBestMatch')
            ->willReturn(new PublicationPierre($this->publication, $this->pierre, 0.9));
    }

    public function testNoAiLabelYetIsRejected(): void
    {
        $this->publicationPierres = $this->createMock(PublicationPierreRepository::class);
        $this->publicationPierres->method('findBestMatch')->willReturn(null);
        $service = new ValidationService($this->em, $this->validations, $this->publicationPierres, $this->adminSettings, $this->messageBus);

        $this->expectException(ValidationPayloadException::class);

        $service->submitValidation($this->publication, $this->makeUser(50), Validation::ACTION_CONFIRM);
    }

    public function testCorrectWithoutProposedLabelIsRejected(): void
    {
        $this->em->expects($this->never())->method('persist');
        $this->expectException(ValidationPayloadException::class);

        $this->service->submitValidation($this->publication, $this->makeUser(50), Validation::ACTION_CORRECT, null);
    }

    public function testConfirmWithProposedLabelIsRejected(): void
    {
        $this->expectException(ValidationPayloadException::class);

        $this->service->submitValidation($this->publication, $this->makeUser(50), Validation::ACTION_CONFIRM, 'Quartz rose');
    }

    public function testNewSubmissionPersistsAndDispatches(): void
    {
        $validator = $this->makeUser(50);
        $this->validations->method('findOneByPublicationAndUser')->willReturn(null);

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Validation::class));
        $this->em->expects($this->once())->method('flush');
        $this->messageBus->expects($this->exactly(2))->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $validation = $this->service->submitValidation($this->publication, $validator, Validation::ACTION_CONFIRM);

        $this->assertSame(Validation::ACTION_CONFIRM, $validation->getAction());
        $this->assertSame(50, $validation->getTrustScoreSnapshot());
        $this->assertSame($this->pierre, $validation->getPierre());
    }

    public function testResubmissionUpdatesExistingRowWithoutPersist(): void
    {
        $validator = $this->makeUser(50);
        $existing = new Validation($validator, $this->publication, $this->pierre, 30);
        $this->validations->method('findOneByPublicationAndUser')->willReturn($existing);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $validator->setTrustScore(90);

        $validation = $this->service->submitValidation($this->publication, $validator, Validation::ACTION_REJECT);

        $this->assertSame($existing, $validation);
        $this->assertSame(Validation::ACTION_REJECT, $validation->getAction());
        // CA-2 : le nouveau snapshot reflète le Trust Score au moment de
        // CETTE resoumission, pas celui figé lors de la première.
        $this->assertSame(90, $validation->getTrustScoreSnapshot());
    }

    private function makeUser(int $trustScore): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole('USER');
        $user->setTrustScore($trustScore);

        return $user;
    }
}
