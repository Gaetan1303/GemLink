<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-admin',
    description: 'Create or update the GemLink administrator from ADMIN_SEED_* environment variables.',
)]
final class SeedAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $adminSeedEmail,
        private readonly string $adminSeedUsername,
        private readonly string $adminSeedPassword,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = mb_strtolower(trim($this->adminSeedEmail));
        $username = trim($this->adminSeedUsername);
        $password = $this->adminSeedPassword;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>ADMIN_SEED_EMAIL must contain a valid email address.</error>');
            return Command::INVALID;
        }

        if ($username === '' || mb_strlen($username) > 30) {
            $output->writeln('<error>ADMIN_SEED_USERNAME must contain between 1 and 30 characters.</error>');
            return Command::INVALID;
        }

        if (strlen($password) < 12) {
            $output->writeln('<error>ADMIN_SEED_PASSWORD must contain at least 12 characters.</error>');
            return Command::INVALID;
        }

        $admin = $this->users->findOneBy(['email' => $email]);
        $created = false;

        if (!$admin instanceof User) {
            $admin = new User();
            $admin->setEmail($email);
            $created = true;
        }

        $admin
            ->setUsername($username)
            ->setRole('ADMIN')
            ->setStatus('ACTIVE')
            ->setPasswordHash($this->passwordHasher->hashPassword($admin, $password));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $output->writeln(sprintf(
            '<info>Admin %s: %s (%s)</info>',
            $created ? 'created' : 'updated',
            $admin->getUsername(),
            $admin->getEmail(),
        ));

        return Command::SUCCESS;
    }
}
