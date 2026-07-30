<?php

namespace App\MessageHandler;

use App\Entity\Publication;
use App\Message\RecalculateConsensusMessage;
use App\Repository\PierreRepository;
use App\Repository\PublicationPierreRepository;
use App\Repository\PublicationRepository;
use App\Service\ConsensusCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.7 CA-4 : applique le consensus pondéré à la publication lorsqu'il
 * dépasse le seuil Admin.
 *
 * Le label gagnant est écrit dans publication_pierre via upsertMatch()
 * (même mécanisme que AnalyzeMediaMessageHandler pour l'IA), pas dans une
 * colonne dénormalisée sur Publication.
 */
#[AsMessageHandler]
final class RecalculateConsensusHandler
{
    public function __construct(
        private readonly PublicationRepository $publications,
        private readonly PierreRepository $pierres,
        private readonly PublicationPierreRepository $publicationPierres,
        private readonly ConsensusCalculatorService $consensusCalculator,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecalculateConsensusMessage $message): void
    {
        $publication = $this->publications->find(Uuid::fromString($message->getPublicationId()));

        if ($publication === null || $publication->isDeleted()) {
            return;
        }

        $result = $this->consensusCalculator->calculate($publication);

        if (!$result->isValidated || $result->winningPierreId === null) {
            return;
        }

        $winningPierre = $this->pierres->find($result->winningPierreId);

        if ($winningPierre === null) {
            $this->logger->error('Consensus winning pierre not found.', [
                'pierreId' => (string) $result->winningPierreId,
                'publicationId' => (string) $publication->getId(),
            ]);

            return;
        }

        $this->publicationPierres->upsertMatch($publication, $winningPierre, $result->score);

        $publication->setStatus(Publication::STATUS_COMMUNITY_VALIDATED);
        $this->em->flush();
    }
}
