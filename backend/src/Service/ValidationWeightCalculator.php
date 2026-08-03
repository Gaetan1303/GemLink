<?php

namespace App\Service;

/**
 * US 2.7 CA-3 : calcule le poids d'une validation communautaire à partir
 * du Trust Score du validateur au moment de la soumission. Pondération
 * linéaire directe : un Trust Score de 80 pèse bien 4x plus qu'un Trust
 * Score de 20. Le poids n'est pas stocké en base (pas de colonne weight
 * sur validation) : il est recalculé à la volée à partir de
 * trust_score_snapshot à chaque calcul de consensus.
 */
class ValidationWeightCalculator
{
    private const MIN_WEIGHT = 1.0;

    /**
     * Plancher à 1 pour éviter qu'un Trust Score de 0 annule complètement
     * la voix d'un utilisateur.
     */
    public function fromTrustScore(int $trustScore): float
    {
        return max((float) $trustScore, self::MIN_WEIGHT);
    }
}
