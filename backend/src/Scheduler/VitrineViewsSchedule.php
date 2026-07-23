<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\FlushVitrineViewsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * US 4.2 - CA-2
 *
 * Déclenche FlushVitrineViewsMessage toutes les 60 secondes.
 *
 * ⚠️ Prérequis :
 *   composer require symfony/scheduler (dans le container php)
 *
 * ⚠️ Le transport "scheduler_vitrine_views" doit être routé vers le message
 * dans config/packages/messenger.yaml, ex :
 *
 * framework:
 *   messenger:
 *     transports:
 *       scheduler_vitrine_views: 'scheduler://vitrine_views'
 *     routing:
 *       App\Message\FlushVitrineViewsMessage: scheduler_vitrine_views
 *
 * Et un worker dédié doit consommer ce transport (ajouter au compose.yaml,
 * même pattern que le worker ai_analysis déjà en place) :
 *
 *   php bin/console messenger:consume scheduler_vitrine_views
 */
#[AsSchedule('vitrine_views')]
class VitrineViewsSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::every('60 seconds', new FlushVitrineViewsMessage())
            );
    }
}
