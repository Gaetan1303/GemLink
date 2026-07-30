<?php



namespace App\Tests\EventListener;

use App\Entity\Publication;
use App\Entity\User;
use App\EventListener\AnalyzeMediaFailureListener;
use App\Message\AnalyzeMediaMessage;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * US 2.1 CA-3 : ANALYSIS_FAILED n'est posé qu'après épuisement des retries.
 */
final class AnalyzeMediaFailureListenerTest extends TestCase
{
    private PublicationRepository&MockObject $publications;
    private EntityManagerInterface&MockObject $em;
    private AnalyzeMediaFailureListener $listener;

    protected function setUp(): void
    {
        $this->publications = $this->createMock(PublicationRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->listener = new AnalyzeMediaFailureListener(
            $this->publications,
            $this->em,
            new NullLogger(),
            $this->createMock(MailerInterface::class),
            'noreply@gem-link.org',
            'GemLink',
        );
    }

    public function testIgnoresEventsForOtherMessageTypes(): void
    {
        $this->publications->expects($this->never())->method('find');
        $this->em->expects($this->never())->method('flush');

        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'async', new Exception('erreur sans rapport'));

        $this->listener->__invoke($event);
    }

    public function testDoesNothingWhileRetriesRemain(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $this->publications->expects($this->never())->method('find');
        $this->em->expects($this->never())->method('flush');

        $envelope = new Envelope(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));
        $event = new WorkerMessageFailedEvent($envelope, 'async', new Exception('erreur transitoire'));
        $event->setForRetry();

        $this->listener->__invoke($event);
    }

    public function testMarksPublicationAsAnalysisFailedWhenNoRetryLeft(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $this->publications->method('find')->willReturn($publication);
        $this->em->expects($this->once())->method('flush');

        $envelope = new Envelope(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));
        $event = new WorkerMessageFailedEvent($envelope, 'async', new Exception('échec définitif'));

        $this->listener->__invoke($event);

        $this->assertSame(Publication::STATUS_ANALYSIS_FAILED, $publication->getStatus());
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
