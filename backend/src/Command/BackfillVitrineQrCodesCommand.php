<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\VitrineRepository;
use App\Service\VitrineQrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * US 4.2 - CA-3 (rattrapage)
 *
 * Génère rétroactivement le QR code des Vitrines créées avant que
 * VitrineQrCodeService::generateAndStore() soit câblé dans
 * VitrineService::createVitrine(). Sans cette commande, ces Vitrines
 * gardent qr_code_url = NULL indéfiniment (la génération n'a lieu qu'à
 * la création, jamais rejouée automatiquement).
 *
 * Usage :
 *   bin/console app:vitrine:backfill-qr-codes
 *   bin/console app:vitrine:backfill-qr-codes --dry-run
 */
#[AsCommand(
    name: 'app:vitrine:backfill-qr-codes',
    description: 'Génère le QR code des Vitrines existantes qui n\'en ont pas encore',
)]
class BackfillVitrineQrCodesCommand extends Command
{
    public function __construct(
        private readonly VitrineRepository $vitrineRepository,
        private readonly VitrineQrCodeService $qrCodeService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, null, 'Liste les Vitrines concernées sans rien modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $vitrines = $this->vitrineRepository->findAllWithoutQrCode();

        if (empty($vitrines)) {
            $io->success('Toutes les Vitrines ont déjà un QR code.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            '%d Vitrine(s) sans QR code trouvée(s)%s.',
            count($vitrines),
            $dryRun ? ' (dry-run, rien ne sera modifié)' : '',
        ));

        $done = 0;
        $failed = 0;

        foreach ($vitrines as $vitrine) {
            $io->writeln(sprintf('  - %s (%s)', $vitrine->getTitle(), $vitrine->getSlug()));

            if ($dryRun) {
                continue;
            }

            try {
                $qrCodeUrl = $this->qrCodeService->generateAndStore($vitrine->getSlug(), $vitrine->getId());
                $vitrine->setQrCodeUrl($qrCodeUrl);
                ++$done;
            } catch (\Throwable $exception) {
                ++$failed;
                $io->error(sprintf(
                    'Échec pour "%s" (%s) : %s',
                    $vitrine->getTitle(),
                    $vitrine->getSlug(),
                    $exception->getMessage(),
                ));
            }
        }

        if (!$dryRun && $done > 0) {
            $this->em->flush();
        }

        if ($dryRun) {
            $io->comment('Relancez sans --dry-run pour générer réellement les QR codes.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d QR code(s) généré(s), %d échec(s).', $done, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}