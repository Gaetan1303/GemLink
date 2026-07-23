<?php

declare(strict_types=1);

namespace App\Message;

/**
 * US 4.2 - CA-2
 *
 * Message déclenché toutes les 60 secondes par le Symfony Scheduler
 * (cf. App\Scheduler\VitrineViewsSchedule) pour flusher les compteurs
 * de vues Redis vers PostgreSQL.
 *
 * Ce message ne porte aucune donnée : la logique va lire directement
 * l'état courant dans Redis au moment de son traitement.
 */
final class FlushVitrineViewsMessage
{
}
