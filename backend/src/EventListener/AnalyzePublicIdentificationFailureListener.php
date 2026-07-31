<?php
namespace App\EventListener;
use App\Entity\PublicIdentification;
use App\Message\AnalyzePublicIdentificationMessage;
use App\Repository\PublicIdentificationRepository;
use App\Service\PublicIdentificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final class AnalyzePublicIdentificationFailureListener
{
    public function __construct(private readonly PublicIdentificationRepository $identifications, private readonly PublicIdentificationService $service, private readonly EntityManagerInterface $em) {}
    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof AnalyzePublicIdentificationMessage || $event->willRetry()) return;
        $identification = $this->identifications->find(Uuid::fromString($message->getIdentificationId()));
        if (!$identification instanceof PublicIdentification) return;
        $identification->markFailed(); $this->em->flush(); $this->service->releaseActiveLock($identification);
    }
}
