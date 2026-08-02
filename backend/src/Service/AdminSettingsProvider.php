<?php

namespace App\Service;

use App\Repository\ParametreSystemeRepository;

/**
 * US 2.7 : fournit les seuils configurables par l'Admin pour la validation
 * communautaire (CA-4, CA-5). Chaque paramètre a une valeur par défaut
 * raisonnable si l'Admin n'a rien configuré en base.
 */
class AdminSettingsProvider
{
    private const CLE_CONSENSUS_THRESHOLD = 'validation.consensus_threshold';
    private const DEFAULT_CONSENSUS_THRESHOLD = 0.66;

    private const CLE_DATASET_CANDIDATE_TRUST_THRESHOLD = 'validation.dataset_candidate_trust_threshold';
    private const DEFAULT_DATASET_CANDIDATE_TRUST_THRESHOLD = 70;

    /** @var array<string, int> */
    private const POINT_DEFAULTS = [
        PointsService::ACTION_POST_CREATED => 10,
        PointsService::ACTION_LIKE_RECEIVED => 2,
        PointsService::ACTION_VALIDATION_SUBMITTED => 5,
        PointsService::ACTION_VALIDATION_CONSENSUS_CONFIRMED => 15,
    ];

    public function __construct(
        private readonly ParametreSystemeRepository $parametreSystemeRepository,
    ) {
    }

    public function getConsensusThreshold(): float
    {
        $value = $this->resolve(self::CLE_CONSENSUS_THRESHOLD);

        return $value !== null ? (float) $value : self::DEFAULT_CONSENSUS_THRESHOLD;
    }

    public function getDatasetCandidateTrustThreshold(): int
    {
        $value = $this->resolve(self::CLE_DATASET_CANDIDATE_TRUST_THRESHOLD);

        return $value !== null ? (int) $value : self::DEFAULT_DATASET_CANDIDATE_TRUST_THRESHOLD;
    }

    public function getPointsForAction(string $action): int
    {
        if (!isset(self::POINT_DEFAULTS[$action])) {
            throw new \InvalidArgumentException('Action de points inconnue.');
        }

        $value = $this->resolve('points.' . strtolower($action));

        return $value !== null ? (int) $value : self::POINT_DEFAULTS[$action];
    }

    private function resolve(string $cle): ?string
    {
        // The service lives for the duration of a Messenger worker. Do not
        // cache values here: an Admin change must affect subsequent jobs
        // without restarting that worker or deploying the application.
        return $this->parametreSystemeRepository->findOneByCle($cle)?->getValeur();
    }
}
