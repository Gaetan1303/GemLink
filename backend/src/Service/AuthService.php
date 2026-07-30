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
use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository; 


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
    private PasswordResetTokenRepository $passwordResetTokenRepository,
    private int $maxLoginAttempts = 5,
    private int $loginAttemptWindow = 600,
) {
}

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

        ['refreshToken' => $refreshToken, 'refreshTokenExpiresAt' => $expiresAt, 'refreshTokenEntity' => $entity]
            = $this->issueRefreshToken($user);

        $this->em->persist($entity);
        $this->em->flush();

        $jti = bin2hex(random_bytes(16));

        return [
            'token' => $this->jwtManager->createFromPayload($user, [
                'jti' => $jti,
                'email' => $user->getEmail(),
                'id' => $user->getId()->toRfc4122(),
            ]),
            'refreshToken' => $refreshToken,
            'refreshTokenExpiresAt' => $expiresAt,
        ];
    }

    /**
     * US 1.4 : Renouvellement de session (silent refresh).
     *
     * CA-2 : rotation de refresh token — l'ancien est révoqué, un nouveau est émis
     *        à chaque renouvellement réussi.
     *
     * @return array{token: string, refreshToken: string, refreshTokenExpiresAt: DateTimeImmutable}
     *
     * @throws InvalidArgumentException si le refresh token est absent, expiré ou révoqué (→ CA-3 côté client)
     */
    public function refresh(string $rawRefreshToken): array
    {
        if ($rawRefreshToken === '') {
            throw new InvalidArgumentException('Refresh token manquant.');
        }

        $tokenHash = hash('sha256', $rawRefreshToken);
        $refreshToken = $this->refreshTokenRepository->findValidByHash($tokenHash);

        if ($refreshToken === null) {
            // CA-3 : token invalide, expiré ou révoqué → le contrôleur renverra 401
            throw new InvalidArgumentException('Refresh token invalide ou expiré.');
        }

        $user = $refreshToken->getUser();

        // CA-2 : révoquer l'ancien refresh token avant d'en émettre un nouveau
        $refreshToken->revoke();

        // CA-2 : émettre un nouveau refresh token
        ['refreshToken' => $newRawToken, 'refreshTokenExpiresAt' => $expiresAt, 'refreshTokenEntity' => $newEntity]
            = $this->issueRefreshToken($user);

        $this->em->persist($newEntity);
        $this->em->flush();

        // Nouveau JWT avec un nouveau jti (l'ancien jti n'est PAS mis en blocklist :
        // il était déjà expiré au moment où on renouvelle)
        $jti = bin2hex(random_bytes(16));

        return [
            'token' => $this->jwtManager->createFromPayload($user, [
                'jti' => $jti,
                'email' => $user->getEmail(),
                'id' => $user->getId()->toRfc4122(),
            ]),
            'refreshToken' => $newRawToken,
            'refreshTokenExpiresAt' => $expiresAt,
        ];
    }

    /**
     * US 1.5 : Déconnexion.
     *
     * CA-1 : révoque le refresh token en base + supprime le cookie côté contrôleur.
     * CA-2 : inscrit le jti du JWT en blocklist Redis (TTL = durée résiduelle).
     */
    public function logout(string $rawRefreshToken, string $rawJwt): void
    {
        if ($rawRefreshToken !== '') {
            $tokenHash = hash('sha256', $rawRefreshToken);
            $refreshToken = $this->refreshTokenRepository->findValidByHash($tokenHash);
            if ($refreshToken !== null) {
                $refreshToken->revoke();
                $this->em->flush();
            }
        }

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

    // --- US 1.6 : Réinitialisation du mot de passe ---
 
    /**
     * CA-1 : demande de réinitialisation.
     * Retourne TOUJOURS sans lever d'exception, que l'email existe ou non,
     * pour ne pas permettre l'énumération de comptes.
     * L'email de reset n'est envoyé que si l'adresse est connue en base.
     */
    public function requestPasswordReset(string $email): void
    {
        $email = mb_strtolower(trim($email));
 
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return; // CA-1 : silencieux, pas d'exception
        }
 
        $user = $this->userRepository->findOneBy(['email' => $email]);
 
        // CA-1 : on sort silencieusement si l'email est inconnu
        if (!$user instanceof User) {
            return;
        }
 
        // CA-2 : token signé HMAC, TTL 1 heure
        $signed = $this->emailValidationTokenSigner->createSignedToken(
            $user->getId()->toRfc4122(),
            ttlSeconds: 3600
        );
 
        // CA-2 : seul le hash SHA-256 est persisté en base
        $resetToken = new PasswordResetToken();
        $resetToken->setUser($user);
        $resetToken->setToken(hash('sha256', $signed['token']));
        $resetToken->setExpiresAt($signed['expiresAt']);
 
        $user->addPasswordResetToken($resetToken);
        $this->em->persist($resetToken);
        $this->em->flush();
 
        // Envoi asynchrone via Messenger
        $this->messageBus->dispatch(new \App\Message\SendEmailMessage(
            to: $user->getEmail(),
            subject: 'Réinitialisation de votre mot de passe GemLink',
            template: 'emails/password_reset.html.twig',
            templateData: [
                'username' => $user->getUsername(),
                'resetUrl' => sprintf('%s/auth/reset-password/%s', $this->frontendUrl, $signed['token']),
            ],
        ));
    }
 
    /**
     * CA-2 / CA-3 / CA-4 : confirmation et application du nouveau mot de passe.
     *
     * @throws InvalidArgumentException si le token est invalide, expiré ou déjà utilisé,
     *                                  ou si le mot de passe ne respecte pas la politique (CA-4).
     */
    public function resetPassword(string $rawToken, string $newPassword): void
    {
        // CA-4 : validation du mot de passe en amont, avant toute vérification du token,
        // pour donner un retour immédiat sur la politique de sécurité.
        if (!preg_match(self::PASSWORD_PATTERN, $newPassword)) {
            throw new InvalidArgumentException(
                'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.'
            );
        }
 
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            throw new InvalidArgumentException('Token de réinitialisation invalide.');
        }
 
        // CA-2 : vérification de la signature HMAC avant la requête DB
        try {
            $claims = $this->emailValidationTokenSigner->decodeAndVerify($rawToken);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('Lien de réinitialisation invalide ou expiré.');
        }
 
        // CA-2 : recherche par hash SHA-256, non utilisé, non expiré
        $resetToken = $this->passwordResetTokenRepository->findValidByHash(hash('sha256', $rawToken));
 
        if ($resetToken === null) {
            throw new InvalidArgumentException('Lien de réinitialisation invalide ou déjà utilisé.');
        }
 
        // Vérification de cohérence : le token appartient bien à l'utilisateur signataire
        if ($resetToken->getUser()->getId()->toRfc4122() !== $claims['sub']) {
            throw new InvalidArgumentException('Lien de réinitialisation invalide ou expiré.');
        }
 
        $user = $resetToken->getUser();
 
        // Mise à jour du mot de passe
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $newPassword));
 
        // CA-2 : marquer le token comme utilisé (usage unique)
        $resetToken->setUsed(true);
 
        // CA-3 : révoquer toutes les sessions actives via une requête DQL groupée
        $this->refreshTokenRepository->revokeAllActiveForUser($user);
 
        $this->em->flush();
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


    // ── Helpers privés ─────────────────────────────────────────────────────────

    /**
     * @return array{refreshToken: string, refreshTokenExpiresAt: DateTimeImmutable, refreshTokenEntity: RefreshToken}
     */
    private function issueRefreshToken(User $user): array
    {
        $raw = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable())->modify(sprintf('+%d seconds', self::REFRESH_TOKEN_TTL_SECONDS));

        return [
            'refreshToken' => $raw,
            'refreshTokenExpiresAt' => $expiresAt,
            'refreshTokenEntity' => new RefreshToken($user, hash('sha256', $raw), $expiresAt),
        ];
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
        return $this->getLoginAttemptState($email)['blocked_until'] > time();
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
     * Usage : logout uniquement, après vérification préalable par le firewall Symfony.
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
