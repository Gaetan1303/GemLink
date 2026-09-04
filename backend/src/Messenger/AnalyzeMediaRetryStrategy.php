<?php

namespace App\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * US 3.1 CA-3 : délais de retry fixes pour l'analyse IA — 30 s, 2 min, 10 min,
 * 3 tentatives maximum.
 *
 * La stratégie "multiplier" générique de Symfony (delay * multiplier^n) ne
 * permet pas d'exprimer cette suite avec un facteur constant : 30s -> 120s
 * est un x4, mais 120s -> 600s est un x5. D'où cette implémentation dédiée,
 * appliquée uniquement au transport `ai_analysis` (config/packages/messenger.yaml)
 * — le transport `async` (emails) garde sa propre stratégie générique.
 */
final class AnalyzeMediaRetryStrategy implements RetryStrategyInterface
{
    /** @var list<int> délais en millisecondes, dans l'ordre des tentatives (1ère, 2e, 3e) */
    private const DELAYS_MS = [30_000, 120_000, 600_000];

    public function isRetryable(Envelope $envelope, ?\Throwable $throwable = null): bool
    {
        return $this->getRetryCount($envelope) < count(self::DELAYS_MS);
    }

    public function getWaitingTime(Envelope $envelope, ?\Throwable $throwable = null): int
    {
        $retryCount = $this->getRetryCount($envelope);

        return self::DELAYS_MS[$retryCount] ?? self::DELAYS_MS[array_key_last(self::DELAYS_MS)];
    }

    /**
     * RedeliveryStamp est ajouté par Messenger à chaque nouvelle tentative ;
     * son absence signifie qu'on est sur le tout premier essai (retryCount 0).
     */
    private function getRetryCount(Envelope $envelope): int
    {
        /** @var RedeliveryStamp|null $stamp */
        $stamp = $envelope->last(RedeliveryStamp::class);

        return $stamp?->getRetryCount() ?? 0;
    }
}
