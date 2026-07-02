<?php

namespace App\Service;

use App\Entity\EmailValidationToken;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\EventListener\JwtBlocklistListener;
use App\Exception\LoginFailedException;
use App\Exception\LoginThrottledException;
use App\Repository\EmailValidationTokenRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AuthService
{
    private const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/';
    public const EMAIL_VALIDATION_SUCCESS_MESSAGE = 'Adresse email validee. Votre compte est maintenant actif.';
    public const LOGIN_ERROR_MESSAGE = 'Identifiants invalides.';
    public const JWT_TTL_SECONDS = 900;
    public const REFRESH_TOKEN_TTL_SECONDS = 604800;

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private EmailValidationTokenRepository $emailValidationTokenRepository,
        private RefreshTokenRepository $refreshTokenRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MessageBusInterface $messageBus,
        private EmailValidationTokenSigner $emailValidationTokenSigner,
        private JWTTokenManagerInterface $jwtManager,
        private CacheInterface $cache,
        private CacheItemPoolInterface $cachePool,
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
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $data['passwordHash']));
        $user->setStatus('PENDING_VALIDATION');

        $this->em->persist($user);

        $plainToken = $this->createAndPersistEmailValidationToken($user);

        $this->em->flush();

        $this->dispatchEmailValidationMessage($user, $plainToken);

        return $user;
    }

    public function validateEmail(string $plainToken): void
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        $claims = $this->emailValidationTokenSigner->decodeAndVerify($plainToken);

        $token = $this->emailValidationTokenRepository->findOneBy(['token' => hash('sha256', $plainToken)]);
        if (!$token instanceof EmailValidationToken) {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        if ($token->getUser()->getId()->toRfc4122() !== $claims['sub']) {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        if ($token->isUsed()) {
            throw new InvalidArgumentException('Lien de validation déjà utilisé.');
        }

        if ($token->getExpiresAt() < new DateTimeImmutable()) {
            throw new InvalidArgumentException('Lien de validation expiré. Vous pouvez demander un nouveau lien.');
        }

        $user = $token->getUser();
        $user->setStatus('ACTIVE');

        foreach ($user->getEmailValidationTokens() as $userToken) {
            if (!$userToken->isUsed()) {
                $userToken->setUsed(true);
            }
        }

        $this->em->flush();
    }

    /**
     * US 1.3 : Connexion.
     *
     * Modification par rapport à la version précédente : le JWT inclut désormais un
     * claim `jti` (JWT ID) — identifiant unique généré à la création — nécessaire pour
     * pouvoir l'inscrire en blocklist Redis lors de la déconnexion (CA-2 US 1.5).
     *
     * @param array{email?: mixed, passwordHash?: mixed} $data
     * @return array{token: string, refreshToken: string, refreshTokenExpiresAt: DateTimeImmutable}
     */
    public function login(array $data): array
    {
        $email = is_string($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : '';
        $passwordHash = is_string($data['passwordHash'] ?? null) ? $data['passwordHash'] : '';

        if ($this->isLoginThrottled($email)) {
            throw new LoginThrottledException(self::LOGIN_ERROR_MESSAGE);
        }

        $user = $email !== '' ? $this->userRepository->findOneBy(['email' => $email]) : null;
        if (
            !$user instanceof User
            || $user->getStatus() !== 'ACTIVE'
            || !$this->passwordHasher->isPasswordValid($user, $passwordHash)
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

        // CA-2 (US 1.5) : jti unique par token, utilisé comme clé de blocklist Redis à la déconnexion.
        $jti = bin2hex(random_bytes(16));

        return [
            'token' => $this->jwtManager->createFromPayload($user, ['jti' => $jti]),
            'refreshToken' => $refreshToken,
            'refreshTokenExpiresAt' => $refreshTokenExpiresAt,
        ];
    }

    /**
     * US 1.5 : Déconnexion.
     *
     * CA-1 : révoque le refresh token en base + supprime le cookie côté contrôleur.
     * CA-2 : inscrit le jti du JWT en blocklist Redis avec une TTL = durée résiduelle du token.
     *
     * On ne lève pas d'exception si le refresh token est introuvable ou le JWT déjà expiré :
     * la déconnexion doit toujours réussir côté client même en cas d'état incohérent serveur.
     */
    public function logout(string $rawRefreshToken, string $rawJwt): void
    {
        // CA-1 : révoquer le refresh token en base
        if ($rawRefreshToken !== '') {
            $tokenHash = hash('sha256', $rawRefreshToken);
            $refreshToken = $this->refreshTokenRepository->findValidByHash($tokenHash);
            if ($refreshToken !== null) {
                $refreshToken->revoke();
                $this->em->flush();
            }
        }

        // CA-2 : mettre le JWT en blocklist Redis avec TTL = durée de vie résiduelle
        if ($rawJwt !== '') {
            $payload = $this->decodeJwtPayloadUnsafe($rawJwt);
            $jti = isset($payload['jti']) && is_string($payload['jti']) ? $payload['jti'] : '';
            $exp = isset($payload['exp']) && is_int($payload['exp']) ? $payload['exp'] : 0;

            $ttl = $exp - time();

            if ($jti !== '' && $ttl > 0) {
                $item = $this->cachePool->getItem(JwtBlocklistListener::blocklistKey($jti));
                $item->set(true);
                $item->expiresAfter($ttl);
                $this->cachePool->save($item);
            }
        }
    }

    public function resendValidationEmail(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || $user->getStatus() !== 'PENDING_VALIDATION') {
            return;
        }

        $plainToken = $this->createAndPersistEmailValidationToken($user);
        $this->em->flush();

        $this->dispatchEmailValidationMessage($user, $plainToken);
    }

    private function validateRegistrationData(array $data): void
    {
        if (empty($data['email']) || empty($data['username']) || empty($data['passwordHash'])) {
            throw new InvalidArgumentException('Données manquantes.');
        }

        $username = trim($data['username']);
        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            throw new InvalidArgumentException('Pseudo invalide.');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email invalide.');
        }

        if (!preg_match(self::PASSWORD_PATTERN, $data['passwordHash'])) {
            throw new InvalidArgumentException('Mot de passe ne respectant pas la politique de sécurité.');
        }

        if ($this->userRepository->findOneBy(['email' => mb_strtolower($username = trim($data['email']))])) {
            throw new InvalidArgumentException('Compte déjà existant.');
        }
        if ($this->userRepository->findOneBy(['username' => $username])) {
            throw new InvalidArgumentException('Compte déjà existant.');
        }
    }

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
            return ['count' => $count, 'blocked_until' => $blockedUntil];
        });
    }

    private function resetLoginAttempts(string $email): void
    {
        $this->cache->delete($this->loginAttemptCacheKey($email));
    }

    private function loginAttemptCacheKey(string $email): string
    {
        return 'auth_login_attempts_' . hash('sha256', $email);
    }

    private function createAndPersistEmailValidationToken(User $user): string
    {
        $signed = $this->emailValidationTokenSigner->createSignedToken($user->getId()->toRfc4122());

        $token = new EmailValidationToken();
        $token->setUser($user);
        $token->setToken(hash('sha256', $signed['token']));
        $token->setExpiresAt($signed['expiresAt']);
        $user->addEmailValidationToken($token);
        $this->em->persist($token);

        return $signed['token'];
    }

    private function dispatchEmailValidationMessage(User $user, string $signedToken): void
    {
        $this->messageBus->dispatch(new \App\Message\SendEmailMessage(
            to: $user->getEmail(),
            subject: 'Confirmez votre inscription sur GemLink',
            template: 'emails/validation.html.twig',
            templateData: [
                'username' => $user->getUsername(),
                'validationUrl' => sprintf('%s/auth/validate-email/%s', $this->frontendUrl, $signedToken),
            ],
        ));
    }

    private function progressiveDelay(int $failedAttempts): int
    {
        return 60 * (2 ** ($failedAttempts - $this->maxLoginAttempts));
    }

    /**
     * Décode le payload JWT sans vérification de signature.
     * Utilisé uniquement pour extraire `jti` et `exp` lors de la déconnexion :
     * la signature a déjà été vérifiée par LexikJWT en amont (firewall api).
     *
     * @return array<string, mixed>
     */
    private function decodeJwtPayloadUnsafe(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true), true);

        return is_array($payload) ? $payload : [];
    }
}