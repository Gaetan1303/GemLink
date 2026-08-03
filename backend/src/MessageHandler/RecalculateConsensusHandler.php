<?php

namespace App\MessageHandler;

use App\Entity\Publication;
use App\Entity\Validation;
use App\Message\RecalculateConsensusMessage;
use App\Message\AwardPointsMessage;
use App\Repository\PierreRepository;
use App\Repository\PublicationPierreRepository;
use App\Repository\PublicationRepository;
use App\Service\ConsensusCalculatorService;
use App\Service\PointsService;
use App\Service\TrustScoreService;
use App\Repository\ValidationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

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
        private readonly ValidationRepository $validations,
        private readonly MessageBusInterface $messageBus,
        private readonly ?TrustScoreService $trustScores = null,
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

        // Each supporting validation has its own source id, so a later
        // consensus recalculation or a Messenger retry cannot award twice.
        $validators = [];
        foreach ($this->validations->findByPublication($publication) as $validation) {
            $validators[$validation->getUser()->getId()->toRfc4122()] = $validation->getUser();
            if (!$this->supportsConsensus($validation, $winningPierre)) {
                continue;
            }

            $this->messageBus->dispatch(new AwardPointsMessage(
                $validation->getUser()->getId()->toRfc4122(),
                PointsService::ACTION_VALIDATION_CONSENSUS_CONFIRMED,
                $validation->getId()->toRfc4122(),
            ));
        }
        foreach ($validators as $validator) {
            $this->trustScores?->recalculate($validator);
        }
    }

    private function supportsConsensus(Validation $validation, \App\Entity\Pierre $winningPierre): bool
    {
        if ($validation->getAction() === Validation::ACTION_CONFIRM) {
            return $validation->getPierre()->getId()->equals($winningPierre->getId());
        }

        return $validation->getAction() === Validation::ACTION_CORRECT
            && $validation->getProposedLabel() !== null
            && mb_strtolower(trim($validation->getProposedLabel())) === mb_strtolower($winningPierre->getName());
    }
}
