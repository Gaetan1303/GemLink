<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Compte de développement chargé avec `doctrine:fixtures:load`.
 *
 * Identifiants : admin@gemlink.local / ChangeMe!2026
 * Cette fixture n'est enregistrée qu'en environnements dev et test.
 */
final class AdminFixture extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin
            ->setUsername('admin')
            ->setEmail('admin@gemlink.local')
            ->setPasswordHash($this->passwordHasher->hashPassword($admin, 'ChangeMe!2026'))
            ->setRole('ADMIN')
            ->setStatus('ACTIVE');

        $manager->persist($admin);
        $manager->flush();
    }
}
