<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\FlushVitrineViewsMessage;
use App\Repository\VitrineRepository;
use App\Service\VitrineViewCounterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * US 4.2 - CA-2
 *
 * Handler exécuté par le Scheduler toutes les 60 secondes.
 * Contient la même logique que FlushVitrineViewsCommand (utilisable en CLI
 * pour du debug manuel), les deux délèguent à VitrineViewCounterService.
 */
#[AsMessageHandler]
final class FlushVitrineViewsMessageHandler
{
    public function __construct(
        private readonly VitrineViewCounterService $viewCounter,
        private readonly VitrineRepository $vitrineRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FlushVitrineViewsMessage $message): void
    {
        $pending = $this->viewCounter->flushPending();

        if (empty($pending)) {
            return;
        }

        foreach ($pending as $vitrineId => $count) {
            $this->vitrineRepository->incrementViewCount($vitrineId, $count);
        }

        $this->logger->info('Vitrine views flushed', [
            'vitrines_updated' => count($pending),
            'total_views' => array_sum($pending),
        ]);
    }
}
