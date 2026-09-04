<?php

namespace App\Service;

use App\Entity\Publication;
use App\Entity\Validation;
use App\Repository\PierreRepository;
use App\Repository\ValidationRepository;
use Symfony\Component\Uid\Uuid;

final class ConsensusResult
{
    public function __construct(
        public readonly ?Uuid $winningPierreId,
        public readonly float $score,
        public readonly bool $isValidated,
    ) {
    }

    public static function none(): self
    {
        return new self(null, 0.0, false);
    }
}

/**
 * US 2.7 CA-3/CA-4 : calcule le score de consensus pondéré d'une publication.
 *
 * Chaque validateur n'a qu'une seule ligne (contrainte uq_validation_pub_user),
 * donc contrairement à un modèle append-only, findByPublication() donne déjà
 * directement l'ensemble des votes actuels — pas de logique de "dernière
 * validation par validateur" à appliquer ici.
 */
class ConsensusCalculatorService
{
    public function __construct(
        private readonly ValidationRepository $validations,
        private readonly PierreRepository $pierres,
        private readonly AdminSettingsProvider $adminSettings,
    ) {
    }

    public function calculate(Publication $publication): ConsensusResult
    {
        $validations = $this->validations->findByPublication($publication);

        if ($validations === []) {
            return ConsensusResult::none();
        }

        $weightByLabel = [];
        $totalWeight = 0.0;

        foreach ($validations as $validation) {
            $weight = max((float) $validation->getTrustScoreSnapshot(), 1.0);
            $totalWeight += $weight;

            // REJECT ne vote pour aucun label : il dilue le consensus du
            // label courant sans en proposer un autre.
            $targetLabelId = match ($validation->getAction()) {
                Validation::ACTION_CONFIRM => $validation->getPierre()->getId(),
                Validation::ACTION_CORRECT => $this->resolveProposedLabel($validation),
                default => null,
            };

            if ($targetLabelId !== null) {
                $key = (string) $targetLabelId;
                $weightByLabel[$key] = ($weightByLabel[$key] ?? 0.0) + $weight;
            }
        }

        if ($totalWeight <= 0.0 || $weightByLabel === []) {
            return ConsensusResult::none();
        }

        $winningLabelId = array_search(max($weightByLabel), $weightByLabel, true);
        $consensusScore = $weightByLabel[$winningLabelId] / $totalWeight;

        return new ConsensusResult(
            winningPierreId: Uuid::fromString($winningLabelId),
            score: $consensusScore,
            isValidated: $consensusScore >= $this->adminSettings->getConsensusThreshold(),
        );
    }

    /**
     * proposedLabel est du texte libre (CA-1 : l'autocomplétion catalogue
     * est une aide de saisie, pas une contrainte de stockage). On ne peut
     * le faire compter dans le consensus que s'il correspond à un Pierre
     * existant du catalogue — sinon il dilue le consensus sans le
     * remporter, comme un REJECT, en attendant un futur enrichissement du
     * catalogue à partir de ces propositions non résolues.
     */
    private function resolveProposedLabel(Validation $validation): ?Uuid
    {
        $label = $validation->getProposedLabel();

        if ($label === null || trim($label) === '') {
            return null;
        }

        return $this->pierres->findOneByNameIgnoreCase($label)?->getId();
    }
}
