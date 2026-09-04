<?php

declare(strict_types=1);

namespace App\Service;

use Redis;

/**
 * US 4.2 - CA-2
 *
 * Bufferise en mémoire (Redis) les vues de Vitrine pour éviter une écriture
 * synchrone en base à chaque consultation de la page publique.
 *
 * Structure Redis utilisée :
 *  - vitrine:views:pending:{vitrineId} => compteur (INCR)
 *  - vitrine:views:dirty               => SET des vitrineId ayant des vues en attente
 *
 * Le flush (persistance en base + remise à zéro) est déclenché toutes les
 * 60 secondes par la commande app:vitrine:flush-views (cf. FlushVitrineViewsCommand).
 */
class VitrineViewCounterService
{
    private const PENDING_KEY_PREFIX = 'vitrine:views:pending:';
    private const  DIRTY_SET_KEY = 'vitrine:views:dirty';

    public function __construct(
        private readonly Redis $redis,
    ) {
    }

    /**
     * Incrémente le compteur de vues en attente pour une Vitrine.
     * Appelé à chaque hit de la page publique (VitrinePublicController::show).
     */
    public function incrementView(string $vitrineId): void
    {
        $this->redis->incr(self::PENDING_KEY_PREFIX . $vitrineId);
        $this->redis->sAdd(self::DIRTY_SET_KEY, $vitrineId);
    }

    /**
     * Récupère et vide atomiquement tous les compteurs en attente.
     * Ne renvoie que les vitrines ayant au moins 1 vue en attente.
     *
     * @return array<string, int> vitrineId => nombre de vues à persister
     */
    public function flushPending(): array
    {
        $dirtyIds = $this->redis->sMembers(self::DIRTY_SET_KEY);

        if (empty($dirtyIds)) {
            return [];
        }

        $result = [];

        foreach ($dirtyIds as $vitrineId) {
            $key = self::PENDING_KEY_PREFIX . $vitrineId;

            // GETSET est atomique : on récupère la valeur courante et on
            // remet le compteur à 0 en une seule opération, ce qui évite
            // de perdre des incréments survenus entre la lecture et le reset.
            $count = (int) $this->redis->getSet($key, '0');

            if ($count > 0) {
                $result[$vitrineId] = $count;
            }

            $this->redis->sRem(self::DIRTY_SET_KEY, $vitrineId);

            // Si le compteur est retombé à 0 (aucune nouvelle vue depuis le
            // getSet), on peut nettoyer la clé pour ne pas laisser traîner
            // des clés vides indéfiniment.
            if ($count === 0) {
                $this->redis->del($key);
            }
        }

        return $result;
    }
}
