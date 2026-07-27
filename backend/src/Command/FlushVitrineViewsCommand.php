<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\VitrineRepository;
use App\Service\VitrineViewCounterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * US 4.2 - CA-2
 *
 * Persiste en base les compteurs de vues bufferisés en Redis.
 * Destinée à être exécutée toutes les 60 secondes (cf. Scheduler ou cron externe).
 *
 * Usage manuel (dans le container php) :
 *   docker compose exec php bin/console app:vitrine:flush-views
 */
#[AsCommand(
    name: 'app:vitrine:flush-views',
    description: 'Persiste en base les compteurs de vues de Vitrine bufferisés en Redis',
)]
class FlushVitrineViewsCommand extends Command
{
    public function __construct(
        private readonly VitrineViewCounterService $viewCounter,
        private readonly VitrineRepository $vitrineRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pending = $this->viewCounter->flushPending();

        if (empty($pending)) {
            $io->comment('Aucune vue en attente.');

            return Command::SUCCESS;
        }

        $updated = 0;

        foreach ($pending as $vitrineId => $count) {
            $this->vitrineRepository->incrementViewCount($vitrineId, $count);
            ++$updated;
        }

        $io->success(sprintf(
            '%d vitrine(s) mise(s) à jour, %d vue(s) persistée(s) au total.',
            $updated,
            array_sum($pending),
        ));

        return Command::SUCCESS;
    }
}
