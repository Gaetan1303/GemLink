<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Publication;
use App\Message\AnalyzeMediaMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * US 2.1 — correspond à la boîte "IAOrchestration : Messenger queue · Retry
 * exponentiel" du diagramme d'architecture. Seul point d'entrée métier pour
 * déclencher une analyse IA : PostService ne connaît pas Messenger, seulement
 * ce service. Le retry exponentiel est configuré au niveau du transport
 * (config/packages/messenger.yaml, retry_strategy) et appliqué automatiquement
 * par Symfony Messenger quand AnalyzeMediaMessageHandler relance une exception ;
 * voir App\EventListener\AnalyzeMediaFailureListener pour la bascule en
 * ANALYSIS_FAILED une fois les tentatives épuisées.
 */
class AiOrchestrationService
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function requestAnalysis(Publication $publication): void
    {
        $this->messageBus->dispatch(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));
    }
}
