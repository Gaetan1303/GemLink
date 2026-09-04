<?php

namespace App\Command;

use App\Service\LeaderboardService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:leaderboard:rebuild', description: 'Reconstruit le classement Redis depuis PostgreSQL.')]
final class RebuildLeaderboardCommand extends Command
{
    public function __construct(private readonly LeaderboardService $leaderboard) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('%d utilisateurs classés.', $this->leaderboard->rebuild()));
        return Command::SUCCESS;
    }
}
