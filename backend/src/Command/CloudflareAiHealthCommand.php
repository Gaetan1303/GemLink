<?php

namespace App\Command;

use App\Service\CloudflareAiService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:cloudflare-ai:health', description: 'Explicit secondary AI inference probe (maximum 4 output tokens).')]
final class CloudflareAiHealthCommand extends Command
{
    public function __construct(private readonly CloudflareAiService $ai) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $healthy = $this->ai->healthCheck();
        $output->writeln($healthy ? 'CLOUDFLARE_AI_HEALTHY' : 'CLOUDFLARE_AI_UNAVAILABLE');
        return $healthy ? Command::SUCCESS : Command::FAILURE;
    }
}
