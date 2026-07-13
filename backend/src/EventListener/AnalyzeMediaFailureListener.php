<?php



namespace App\EventListener;

use App\Entity\Publication;
use App\Message\AnalyzeMediaMessage;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.1 CA-3 : bascule un post en ANALYSIS_FAILED uniquement lorsque
 * Symfony Messenger a épuisé toutes les tentatives de retry exponentiel
 * (voir AnalyzeMediaMessageHandler et messenger.yaml retry_strategy). Une
 * erreur transitoire isolée ne doit jamais marquer le post en échec avant
 * la dernière tentative.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final class AnalyzeMediaFailureListener
{
    public function __construct(
        private readonly PublicationRepository $publications,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof AnalyzeMediaMessage || $event->willRetry()) {
            return;
        }

        $publication = $this->publications->find(Uuid::fromString($message->getPublicationId()));

        if ($publication === null || $publication->isDeleted()) {
            return;
        }

        $this->logger->error('Analyse IA définitivement échouée après épuisement des tentatives de retry.', [
            'publicationId' => $publication->getId()->toRfc4122(),
            'exception' => $event->getThrowable()->getMessage(),
        ]);

        $publication->setStatus(Publication::STATUS_ANALYSIS_FAILED);
        $this->em->flush();
    }
}
