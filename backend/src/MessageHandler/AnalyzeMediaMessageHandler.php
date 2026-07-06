<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Publication;
use App\Message\AnalyzeMediaMessage;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * US 2.1 CA-3 : consomme AnalyzeMediaMessage en tâche de fond (IA Worker) et
 * appelle le service FastAPI (pipeline YOLO -> ViT -> CLIP) pour identifier
 * la pierre.
 *
 * Retry exponentiel (diagramme d'architecture, boîte IAOrchestration) : en
 * cas d'échec, on relance l'exception plutôt que de l'avaler. C'est Symfony
 * Messenger qui reprogramme automatiquement le message avec un backoff
 * exponentiel (voir config/packages/messenger.yaml, retry_strategy). La
 * bascule définitive en ANALYSIS_FAILED, une fois les tentatives épuisées,
 * est gérée par App\EventListener\AnalyzeMediaFailureListener — jamais ici,
 * pour ne pas marquer un post en échec après une simple erreur transitoire.
 *
 * NOTE architecture (en cours de stabilisation avec StoneVision) : cet appel
 * HTTP synchrone au FastAPI est un choix MVP. Le flux de retour définitif
 * (polling vs WebSocket) et la communication IA Worker <-> FastAPI sur
 * Railway restent à finaliser ; voir doc/cahier.md.
 */
#[AsMessageHandler]
final class AnalyzeMediaMessageHandler
{
    public function __construct(
        private readonly PublicationRepository $publications,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly string $aiServiceUrl,
    ) {
    }

    public function __invoke(AnalyzeMediaMessage $message): void
    {
        $publication = $this->publications->find(Uuid::fromString($message->getPublicationId()));

        if ($publication === null || $publication->isDeleted()) {
            // CA-4 : le post a pu être supprimé pendant que le message était en file.
            return;
        }

        $response = $this->httpClient->request('POST', rtrim($this->aiServiceUrl, '/') . '/analyze', [
            'json' => [
                'publicationId' => $publication->getId()->toRfc4122(),
                'mediaUrl' => $publication->getMediaUrl(),
                'mediaType' => $publication->getMediaType(),
            ],
            'timeout' => 30,
        ]);

        if ($response->getStatusCode() >= 400) {
            // Relancée : déclenche le retry exponentiel de Messenger.
            throw new RuntimeException(sprintf('Réponse du service IA invalide (HTTP %d).', $response->getStatusCode()));
        }

        $publication->setStatus(Publication::STATUS_ANALYZED);
        $this->em->flush();
    }
}
