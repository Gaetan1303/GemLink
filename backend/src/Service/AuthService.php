<?php

namespace App\Service;

use App\Entity\EmailValidationToken;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Exception\LoginFailedException;
use App\Exception\LoginThrottledException;
use App\Repository\EmailValidationTokenRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AuthService
{
    private const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/';
    public const LOGIN_ERROR_MESSAGE = 'Identifiants invalides.';
    public const JWT_TTL_SECONDS = 900;
    public const REFRESH_TOKEN_TTL_SECONDS = 604800;

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private EmailValidationTokenRepository $emailValidationTokenRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MessageBusInterface $messageBus,
        private JWTTokenManagerInterface $jwtManager,
        private CacheInterface $cache,
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

    public function validateEmail(string $plainToken): void
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        $token = $this->emailValidationTokenRepository->findOneBy(['token' => $plainToken]);
        if (!$token instanceof EmailValidationToken) {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        if ($token->isUsed()) {
            throw new InvalidArgumentException('Token de validation deja utilise.');
        }

        if ($token->getExpiresAt() < new DateTimeImmutable()) {
            throw new InvalidArgumentException('Token de validation expire.');
        }

        $user = $token->getUser();
        $user->setStatus('ACTIVE');
        $token->setUsed(true);

        $this->em->flush();
    }

    /**
     * @param array{email?: mixed, password?: mixed} $data
     *
     * @return array{token: string, refreshToken: string, refreshTokenExpiresAt: DateTimeImmutable}
     */
    public function login(array $data): array
    {
        $email = is_string($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : '';
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';

        if ($this->isLoginThrottled($email)) {
            throw new LoginThrottledException(self::LOGIN_ERROR_MESSAGE);
        }

        $user = $email !== '' ? $this->userRepository->findOneBy(['email' => $email]) : null;
        if (
            !$user instanceof User
            || $user->getStatus() !== 'ACTIVE'
            || !$this->passwordHasher->isPasswordValid($user, $password)
        ) {
            $this->recordFailedLogin($email);

            throw new LoginFailedException(self::LOGIN_ERROR_MESSAGE);
        }

        $this->resetLoginAttempts($email);

        $refreshToken = bin2hex(random_bytes(32));
        $refreshTokenExpiresAt = (new DateTimeImmutable())->modify('+7 days');

        $this->em->persist(new RefreshToken(
            $user,
            hash('sha256', $refreshToken),
            $refreshTokenExpiresAt
        ));
        $this->em->flush();

        return [
            'token' => $this->jwtManager->create($user),
            'refreshToken' => $refreshToken,
            'refreshTokenExpiresAt' => $refreshTokenExpiresAt,
        ];
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

    /**
     * @return array{count: int, blocked_until: int}
     */
    private function getLoginAttemptState(string $email): array
    {
        return $this->cache->get($this->loginAttemptCacheKey($email), function (ItemInterface $item): array {
            $item->expiresAfter($this->loginAttemptWindow);

            return ['count' => 0, 'blocked_until' => 0];
        });
    }

    private function isLoginThrottled(string $email): bool
    {
        $state = $this->getLoginAttemptState($email);

        return $state['blocked_until'] > time();
    }

    private function recordFailedLogin(string $email): void
    {
        $state = $this->getLoginAttemptState($email);
        $count = $state['count'] + 1;
        $blockedUntil = $state['blocked_until'];

        if ($count >= $this->maxLoginAttempts) {
            $blockedUntil = time() + $this->progressiveDelay($count);
        }

        $key = $this->loginAttemptCacheKey($email);
        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($count, $blockedUntil): array {
            $item->expiresAfter(max($this->loginAttemptWindow, $blockedUntil - time()));

            return [
                'count' => $count,
                'blocked_until' => $blockedUntil,
            ];
        });
    }

    private function resetLoginAttempts(string $email): void
    {
        $this->cache->delete($this->loginAttemptCacheKey($email));
    }

    private function loginAttemptCacheKey(string $email): string
    {
        return 'auth_login_attempts_'.hash('sha256', $email);
    }

    private function progressiveDelay(int $failedAttempts): int
    {
        return 60 * (2 ** ($failedAttempts - $this->maxLoginAttempts));
    }
}
