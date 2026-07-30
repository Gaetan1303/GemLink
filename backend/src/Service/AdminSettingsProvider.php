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

    /** @var array<string, string|null> */
    private array $cache = [];

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

    private function resolve(string $cle): ?string
    {
        if (array_key_exists($cle, $this->cache)) {
            return $this->cache[$cle];
        }

        $parametre = $this->parametreSystemeRepository->findOneByCle($cle);

        return $this->cache[$cle] = $parametre?->getValeur();
    }
}
