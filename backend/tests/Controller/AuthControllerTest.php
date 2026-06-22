<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Exception\LoginFailedException;
use App\Exception\LoginThrottledException;
use App\Service\AuthService;
use App\Tests\Unitaire\Auth\Login;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

require_once dirname(__DIR__).'/unitaire/Auth/Login.php';

final class AuthControllerTest extends WebTestCase
{
    public function testRegisterSuccess(): void
    {
        $client = static::createClient();

        // Préparer un mock pour AuthService afin d'isoler le controller
        $user = new User();
        $user->setEmail('test_controller@example.com');
        $user->setUsername('test_user');
        $user->setPasswordHash('hashed');

        $authMock = $this->createMock(AuthService::class);
        $authMock->expects($this->once())
            ->method('register')
            ->with($this->callback(function ($data) {
                return isset($data['email'], $data['username'], $data['passwordHash']);
            }))
            ->willReturn($user);

        // Enregistrer le mock dans le container du client
        $client->getContainer()->set(AuthService::class, $authMock);

        $client->request(
            'POST',
            '/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test_controller@example.com',
                'username' => 'test_user',
                'passwordHash' => 'Password123!'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(
    'Si ces informations sont valides, un email de confirmation a été envoyé.',
    $responseData['message']);}

    public function testLoginMvpContract(): void
    {
        $login = Login::reussite(
            email: 'active_user@example.com',
            jwt: $this->jwtAvecDureeDeVie(Login::JWT_TTL_SECONDS),
            refreshToken: str_repeat('a', 64)
        );

        $this->assertSame('active_user@example.com', $login->email);
        $this->assertTrue($login->compteActif);
        $this->assertSame(Login::JWT_TTL_SECONDS, $this->jwtTtl($login->jwt));
        $this->assertNotSame('', $this->jwtSignature($login->jwt));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $login->refreshToken);
        $this->assertCount(1, explode('.', $login->refreshToken));
        $this->assertTrue($login->refreshCookieHttpOnly);
        $this->assertTrue($login->refreshCookieSecure);
        $this->assertSame('Strict', $login->refreshCookieSameSite);
        $this->assertSame(Login::REFRESH_TOKEN_TTL_SECONDS, $login->refreshTokenTtl);

        $echec = Login::echecIdentifiantsInvalides('active_user@example.com');
        $emailInconnu = Login::echecIdentifiantsInvalides('unknown_user@example.com');

        $this->assertSame(Login::MESSAGE_IDENTIFIANTS_INVALIDES, $echec->messageErreur);
        $this->assertSame($echec->messageErreur, $emailInconnu->messageErreur);
        $this->assertSame(Login::LOGIN_ATTEMPT_WINDOW_SECONDS, $echec->fenetreTentativesSecondes);
        $this->assertFalse($echec->peutReessayerApres(5));
        $this->assertTrue($echec->peutReessayerApres(4));
        $this->assertSame(60, $echec->delaiProgressifSecondes(5));
        $this->assertSame(120, $echec->delaiProgressifSecondes(6));
    }

    public function testLoginSuccessReturnsJwtAndRefreshTokenCookie(): void
    {
        $client = static::createClient();

        $authMock = $this->createMock(AuthService::class);
        $authMock->expects($this->once())
            ->method('login')
            ->with([
                'email' => 'active_user@example.com',
                'password' => 'Password123!',
            ])
            ->willReturn([
                'token' => $this->jwtAvecDureeDeVie(Login::JWT_TTL_SECONDS),
                'refreshToken' => str_repeat('b', 64),
                'refreshTokenExpiresAt' => new DateTimeImmutable('+7 days'),
            ]);

        $client->getContainer()->set(AuthService::class, $authMock);

        $client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'active_user@example.com',
                'password' => 'Password123!',
            ])
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(Login::JWT_TTL_SECONDS, $this->jwtTtl($responseData['token']));

        $refreshCookie = null;
        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'refresh_token') {
                $refreshCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($refreshCookie);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $refreshCookie->getValue());
        $this->assertTrue($refreshCookie->isHttpOnly());
        $this->assertTrue($refreshCookie->isSecure());
        $this->assertSame('strict', mb_strtolower($refreshCookie->getSameSite()));
    }

    public function testLoginFailureReturnsGenericMessage(): void
    {
        $client = static::createClient();

        $authMock = $this->createMock(AuthService::class);
        $authMock->expects($this->once())
            ->method('login')
            ->willThrowException(new LoginFailedException());

        $client->getContainer()->set(AuthService::class, $authMock);

        $client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'unknown_user@example.com',
                'password' => 'WrongPassword123!',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertSame(
            ['message' => Login::MESSAGE_IDENTIFIANTS_INVALIDES],
            json_decode($client->getResponse()->getContent(), true)
        );
    }

    public function testLoginThrottlingReturnsTooManyRequestsWithGenericMessage(): void
    {
        $client = static::createClient();

        $authMock = $this->createMock(AuthService::class);
        $authMock->expects($this->once())
            ->method('login')
            ->willThrowException(new LoginThrottledException());

        $client->getContainer()->set(AuthService::class, $authMock);

        $client->request(
            'POST',
            '/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'active_user@example.com',
                'password' => 'Password123!',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertSame(
            ['message' => Login::MESSAGE_IDENTIFIANTS_INVALIDES],
            json_decode($client->getResponse()->getContent(), true)
        );
    }

    private function jwtAvecDureeDeVie(int $ttl): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode(['iat' => 1000, 'exp' => 1000 + $ttl]));

        return $header.'.'.$payload.'.signature';
    }

    private function jwtTtl(string $jwt): int
    {
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        $encodedPayload = strtr($parts[1], '-_', '+/');
        $encodedPayload .= str_repeat('=', (4 - strlen($encodedPayload) % 4) % 4);

        $payload = json_decode(base64_decode($encodedPayload), true);
        $this->assertIsArray($payload);

        return $payload['exp'] - $payload['iat'];
    }

    private function jwtSignature(string $jwt): string
    {
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        return $parts[2];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

}
