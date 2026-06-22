<?php

declare(strict_types=1);

namespace App\Tests\Unitaire\Auth;

final class Login
{
    public const JWT_TTL_SECONDS = 900;
    public const REFRESH_TOKEN_TTL_SECONDS = 604800;
    public const MAX_FAILED_ATTEMPTS = 5;
    public const LOGIN_ATTEMPT_WINDOW_SECONDS = 600;
    public const MESSAGE_IDENTIFIANTS_INVALIDES = 'Identifiants invalides.';

    private function __construct(
        public readonly string $email,
        public readonly bool $compteActif = true,
        public readonly ?string $jwt = null,
        public readonly ?string $refreshToken = null,
        public readonly int $refreshTokenTtl = self::REFRESH_TOKEN_TTL_SECONDS,
        public readonly bool $refreshCookieHttpOnly = true,
        public readonly bool $refreshCookieSecure = true,
        public readonly string $refreshCookieSameSite = 'Strict',
        public readonly ?string $messageErreur = null,
        public readonly int $fenetreTentativesSecondes = self::LOGIN_ATTEMPT_WINDOW_SECONDS,
    ) {
    }

    public static function reussite(string $email, string $jwt, string $refreshToken): self
    {
        return new self(
            email: $email,
            jwt: $jwt,
            refreshToken: $refreshToken,
        );
    }

    public static function echecIdentifiantsInvalides(string $email): self
    {
        return new self(
            email: $email,
            messageErreur: self::MESSAGE_IDENTIFIANTS_INVALIDES,
        );
    }

    public function peutReessayerApres(int $tentativesEchouees): bool
    {
        return $tentativesEchouees < self::MAX_FAILED_ATTEMPTS;
    }

    public function delaiProgressifSecondes(int $tentativesEchouees): int
    {
        if ($tentativesEchouees < self::MAX_FAILED_ATTEMPTS) {
            return 0;
        }

        return 60 * (2 ** ($tentativesEchouees - self::MAX_FAILED_ATTEMPTS));
    }
}
