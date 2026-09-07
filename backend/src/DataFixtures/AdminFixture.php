<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Development fixture. For production/demo environments prefer:
 *   php bin/console app:seed-admin
 * which is idempotent and does not purge unrelated data.
 */
final class AdminFixture extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $users,
        private readonly string $adminFixturePassword,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if (strlen($this->adminFixturePassword) < 12) {
            throw new \RuntimeException('ADMIN_FIXTURE_PASSWORD must contain at least 12 characters.');
        }

        $email = 'admin@gemlink.local';
        $admin = $this->users->findOneBy(['email' => $email]) ?? new User();

        $admin
            ->setUsername('admin')
            ->setEmail($email)
            ->setPasswordHash($this->passwordHasher->hashPassword($admin, $this->adminFixturePassword))
            ->setRole('ADMIN')
            ->setStatus('ACTIVE');

        $manager->persist($admin);
        $manager->flush();
    }
}
