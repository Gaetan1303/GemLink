<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Compte de développement chargé avec `doctrine:fixtures:load`.
 *
 * L'adresse est fixe et locale, mais le mot de passe doit être fourni
 * explicitement via ADMIN_FIXTURE_PASSWORD.
 */
final class AdminFixture extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $adminFixturePassword,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if (strlen($this->adminFixturePassword) < 12) {
            throw new \RuntimeException('ADMIN_FIXTURE_PASSWORD must contain at least 12 characters.');
        }

        $admin = new User();
        $admin
            ->setUsername('admin')
            ->setEmail('admin@gemlink.local')
            ->setPasswordHash($this->passwordHasher->hashPassword($admin, $this->adminFixturePassword))
            ->setRole('ADMIN')
            ->setStatus('ACTIVE');

        $manager->persist($admin);
        $manager->flush();
    }
}
