<?php
namespace App\Tests\Unitaire\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\EventListener\JwtBlocklistListener;
use App\Repository\EmailValidationTokenRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\EmailValidationTokenSigner;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;

require_once __DIR__ . '/Deconnexion.php';

/**
 * US 1.5 — Tests unitaires de la logique de déconnexion.
 *
 * Couvre AuthService::logout() (CA-1, CA-2) et JwtBlocklistListener (CA-2).
 * CA-3 (redirection) est testé côté Angular (auth.service.spec.ts).
 */
final class DeconnexionTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Construit un JWT factice avec payload base64url encodé.
     * `decodeJwtPayloadUnsafe()` dans AuthService ne vérifie pas la signature.
     */
    private function makeJwt(array $payload): string
    {
        $header  = rtrim(strtr(base64_encode('{"alg":"HS256","typ":"JWT"}'), '+/', '-_'), '=');
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        return $header . '.' . $encoded . '.fakesignature';
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail('test@gemlink.com');
        $user->setUsername('testuser');
        $user->setPasswordHash('hashed');

        return $user;
    }

    /**
     * Crée un AuthService entièrement mocké.
     * Seuls les mocks utiles au test en cours sont configurés ; les autres
     * sont des doublures neutres.
     */
    private function makeAuthService(
        EntityManagerInterface $em,
        RefreshTokenRepository $refreshTokenRepo,
        CacheItemPoolInterface $cachePool,
    ): AuthService {
        return new AuthService(
            em:                          $em,
            userRepository:              $this->createMock(UserRepository::class),
            emailValidationTokenRepository: $this->createMock(EmailValidationTokenRepository::class),
            refreshTokenRepository:      $refreshTokenRepo,
            passwordHasher:              $this->createMock(UserPasswordHasherInterface::class),
            messageBus:                  $this->createMock(MessageBusInterface::class),
            emailValidationTokenSigner:  $this->createMock(EmailValidationTokenSigner::class),
            jwtManager:                  $this->createMock(JWTTokenManagerInterface::class),
            cache:                       $this->createMock(CacheInterface::class),
            cachePool:                   $cachePool,
            frontendUrl:                 'https://gemlink.com',
            passwordResetTokenRepository: $this->createMock(PasswordResetTokenRepository::class),
        );
    }

    // ── CA-1 : Révocation du refresh token en base ────────────────────────────

    public function testLaDeconnexionRevoqueLeRefreshTokenEnBase(): void
    {
        $rawRefreshToken = bin2hex(random_bytes(32));
        $tokenHash       = hash('sha256', $rawRefreshToken);

        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->expects($this->once())->method('revoke');

        $refreshTokenRepo = $this->createMock(RefreshTokenRepository::class);
        $refreshTokenRepo
            ->expects($this->once())
            ->method('findValidByHash')
            ->with($tokenHash)
            ->willReturn($refreshToken);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = $this->makeAuthService($em, $refreshTokenRepo, $this->createMock(CacheItemPoolInterface::class));
        $service->logout($rawRefreshToken, '');

        // Le contrat est honoré si on arrive ici sans exception
        $contrat = Deconnexion::reussite(ttlResiduelJwt: 0);
        $this->assertTrue($contrat->refreshTokenRevoqueEnBase);
    }

    public function testLaDeconnexionSilencieuseSiRefreshTokenAbsent(): void
{
        // CA-1 : cookie absent (déjà supprimé, ou navigateur trop vieux) → pas d'erreur
        $refreshTokenRepo = $this->createMock(RefreshTokenRepository::class);
        $refreshTokenRepo->expects($this->never())->method('findValidByHash');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = $this->makeAuthService($em, $refreshTokenRepo, $this->createMock(CacheItemPoolInterface::class));
        $service->logout('', '');
    }

    public function testLaDeconnexionSilencieuseSiRefreshTokenIntrouvableEnBase(): void
    {
        // CA-1 : token présent dans le cookie mais déjà révoqué ou expiré en base
        $refreshTokenRepo = $this->createMock(RefreshTokenRepository::class);
        $refreshTokenRepo->method('findValidByHash')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $service = $this->makeAuthService($em, $refreshTokenRepo, $this->createMock(CacheItemPoolInterface::class));

       
        $service->logout(bin2hex(random_bytes(32)), '');
    }

    // ── CA-2 : Blocklist Redis du JWT ─────────────────────────────────────────

    public function testLeJwtEstInscritEnBloclistAvecTtlResiduelle(): void
    {
        $jti = 'jti-test-' . bin2hex(random_bytes(8));
        $exp = time() + 900;
        $rawJwt = $this->makeJwt(['jti' => $jti, 'exp' => $exp]);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())->method('set')->with(true);
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with($this->greaterThan(0));

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool
            ->expects($this->once())
            ->method('getItem')
            ->with(JwtBlocklistListener::blocklistKey($jti))
            ->willReturn($cacheItem);
        $cachePool->expects($this->once())->method('save')->with($cacheItem);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $service = $this->makeAuthService(
            $em,
            $this->createMock(RefreshTokenRepository::class),
            $cachePool,
        );
        $service->logout('', $rawJwt);

        $contrat = Deconnexion::reussite(ttlResiduelJwt: $exp - time());
        $this->assertTrue($contrat->jwtInvalideLEnBloclistRedis);
        $this->assertTrue($contrat->ttlEstCohérenteAvecExpiration($exp, time()));
    }

    public function testLeJwtExpireNEstPasInscritEnBloclist(): void
    {
        // CA-2 : TTL résiduelle ≤ 0 → pas d'écriture Redis (inutile, le token est déjà invalide)
        $rawJwt = $this->makeJwt(['jti' => 'jti-expire', 'exp' => time() - 60]);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->never())->method('getItem');
        $cachePool->expects($this->never())->method('save');

        $service = $this->makeAuthService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(RefreshTokenRepository::class),
            $cachePool,
        );
        $service->logout('', $rawJwt);
    }

    public function testLeJwtSansJtiNEstPasInscritEnBloclist(): void
    {
        // CA-2 : JWT sans jti → impossible de révoquer de manière ciblée → on ignore
        $rawJwt = $this->makeJwt(['exp' => time() + 900]);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->never())->method('getItem');

        $service = $this->makeAuthService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(RefreshTokenRepository::class),
            $cachePool,
        );
        $service->logout('', $rawJwt);
    }

    public function testUneChainJwtVideNProvoquePasDecriture(): void
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->never())->method('getItem');

        $service = $this->makeAuthService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(RefreshTokenRepository::class),
            $cachePool,
        );
        $service->logout('', '');
    }

    public function testLaDeconnexionNeLevePasExceptionSiCookieEtJwtSontAbsents(): void
    {
        // La déconnexion doit toujours réussir, même sans cookies ni token
        $service = $this->makeAuthService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(RefreshTokenRepository::class),
            $this->createMock(CacheItemPoolInterface::class),
        );

        $this->expectNotToPerformAssertions();
        $service->logout('', '');
    }

    // ── JwtBlocklistListener — CA-2 ───────────────────────────────────────────

    public function testLeListenerInvalideLTokenSiJtiEstEnBloclist(): void
    {
        $jti = 'jti-révoqué';

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool
            ->expects($this->once())
            ->method('hasItem')
            ->with(JwtBlocklistListener::blocklistKey($jti))
            ->willReturn(true);

        $listener = new JwtBlocklistListener($cachePool);
        $event    = new JWTDecodedEvent(['jti' => $jti, 'exp' => time() + 900]);

        $listener->onJwtDecoded($event);

        $this->assertFalse($event->isValid(), 'CA-2 : un token dont le jti est en blocklist doit être invalide.');
    }

    public function testLeListenerLaisseLeTokenValideSiJtiAbsentDeBloclist(): void
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->method('hasItem')->willReturn(false);

        $listener = new JwtBlocklistListener($cachePool);
        $event    = new JWTDecodedEvent(['jti' => 'jti-inconnu', 'exp' => time() + 900]);

        $listener->onJwtDecoded($event);

        $this->assertTrue($event->isValid(), 'Un token non présent en blocklist doit rester valide.');
    }

    public function testLeListenerInvalideLTokenSansJti(): void
    {
        // Sécurité défensive : un token sans jti ne peut pas être révoqué → rejeté
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->never())->method('hasItem');

        $listener = new JwtBlocklistListener($cachePool);
        $event    = new JWTDecodedEvent(['exp' => time() + 900]);

        $listener->onJwtDecoded($event);

        $this->assertFalse($event->isValid(), 'Un token sans jti doit être rejeté par défaut.');
    }

    public function testLeListenerInvalideLTokenAvecJtiVide(): void
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects($this->never())->method('hasItem');

        $listener = new JwtBlocklistListener($cachePool);
        $event    = new JWTDecodedEvent(['jti' => '', 'exp' => time() + 900]);

        $listener->onJwtDecoded($event);

        $this->assertFalse($event->isValid());
    }

    public function testLaCleDeBlocklistEstDeterministe(): void
    {
        $jti = 'mon-jti';

        $this->assertSame(
            JwtBlocklistListener::blocklistKey($jti),
            JwtBlocklistListener::blocklistKey($jti),
            'La clé Redis doit être déterministe pour le même jti.'
        );
        $this->assertStringContainsString(
            $jti,
            JwtBlocklistListener::blocklistKey($jti),
            'La clé doit contenir le jti pour faciliter le débogage Redis.'
        );
    }
}
