<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\EmailValidationToken;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use InvalidArgumentException;

class AuthService
{
    private const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/';

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MessageBusInterface $messageBus,
        private string $frontendUrl,
        private int $maxLoginAttempts = 5,
        private int $loginAttemptWindow = 600
    ) {}

    // --- US 1.1 : Inscription ---
    public function register(array $data): User
    {
        $this->validateRegistrationData($data);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setUsername($data['username']);
        // CA-1/CA-2 : le frontend envoie "passwordHash" (mot de passe en clair, nommage transitoire)
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $data['passwordHash']));
        $user->setStatus('PENDING_VALIDATION');

        $this->em->persist($user);

        // CA-4 : token de validation email, lié à l'utilisateur
        $token = new EmailValidationToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32))); // 64 car. hex, cryptographiquement sûr
        $token->setExpiresAt((new DateTimeImmutable())->modify('+24 hours'));
        $user->addEmailValidationToken($token);
        $this->em->persist($token);

        $this->em->flush();

        // CA-4 : envoi asynchrone via Messenger, ne bloque pas la réponse HTTP
        $this->messageBus->dispatch(new \App\Message\SendEmailMessage(
            to: $user->getEmail(),
            subject: 'Confirmez votre inscription sur GemLink',
            template: 'emails/validation.html.twig',
            templateData: [
                'username' => $user->getUsername(),
                'validationUrl' => sprintf('%s/auth/validate-email/%s', $this->frontendUrl, $token->getToken()),
            ],
        ));

        return $user;
    }

    private function validateRegistrationData(array $data): void
    {
        // CA-1 : champs requis
        if (empty($data['email']) || empty($data['username']) || empty($data['passwordHash'])) {
            throw new InvalidArgumentException('Données manquantes.');
        }

        // CA-1 : pseudo alphanumérique, 3-30 caractères
        $username = trim($data['username']);
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            throw new InvalidArgumentException('Pseudo invalide.');
        }

        // CA-1 : email RFC 5322 
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email invalide.');
        }

        // CA-2 : politique de mot de passe revalidée côté serveur 
        if (!preg_match(self::PASSWORD_PATTERN, $data['passwordHash'])) {
            throw new InvalidArgumentException('Mot de passe ne respectant pas la politique de sécurité.');
        }

        // CA-3 : ces vérifications lèvent la même exception générique, 
        if ($this->userRepository->findOneBy(['email' => mb_strtolower($username = trim($data['email']))])) {
            throw new InvalidArgumentException('Compte déjà existant.');
        }
        if ($this->userRepository->findOneBy(['username' => $username])) {
            throw new InvalidArgumentException('Compte déjà existant.');
        }
    }
}