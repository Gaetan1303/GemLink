<?php

namespace App\Service;


use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use InvalidArgumentException;

class AuthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
       
        private UserPasswordHasherInterface $passwordHasher,

        private int $maxLoginAttempts = 5,
        private int $loginAttemptWindow = 600 // 10 minutes
    ) {}

    // --- US 1.1 : Inscription ---
    public function register(array $data): User
    {
        $this->validateRegistrationData($data);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setUsername($data['username']);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $data['password']));
        $user->setStatus('PENDING_VALIDATION'); 

        $this->em->persist($user);
        $this->em->flush();

       

        return $user;
    }

    private function validateRegistrationData(array $data): void
    {
        if (empty($data['email']) || empty($data['username']) || empty($data['password'])) {
            throw new InvalidArgumentException("Données manquantes.");
        }
        if ($this->userRepository->findOneBy(['email' => mb_strtolower(trim($data['email']))])) {
            throw new InvalidArgumentException("Email déjà utilisé.");
        }
        if ($this->userRepository->findOneBy(['username' => trim($data['username'])])) {
            throw new InvalidArgumentException("Nom d'utilisateur déjà utilisé.");
        }
    }

}

   
