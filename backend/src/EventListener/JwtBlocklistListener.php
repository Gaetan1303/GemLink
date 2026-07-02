<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Psr\Cache\CacheItemPoolInterface;

/**
 * CA-2 : sur chaque requête authentifiée par JWT, vérifie si le jti du token
 * figure dans la blocklist Redis. Si c'est le cas, invalide le token pour cette
 * requête, ce qui provoquera un rejet 401 par LexikJWT.
 *
 * La blocklist est alimentée par AuthService::logout().
 * TTL de la clé Redis = durée de vie résiduelle du JWT au moment de la déconnexion.
 */
final readonly class JwtBlocklistListener
{
    public function __construct(private CacheItemPoolInterface $cache) {}

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $jti = $payload['jti'] ?? null;

        if (!is_string($jti) || $jti === '') {
            // Sécurité défensive : un token sans jti ne peut pas être révoqué → on le rejette.
            $event->markAsInvalid();
            return;
        }

        $cacheKey = $this->blocklistKey($jti);

        if ($this->cache->hasItem($cacheKey)) {
            // CA-2 : jti présent dans la blocklist → token révoqué, requête rejetée.
            $event->markAsInvalid();
        }
    }

    public static function blocklistKey(string $jti): string
    {
        return 'jwt_blocklist_' . $jti;
    }
}