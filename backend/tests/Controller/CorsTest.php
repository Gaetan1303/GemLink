<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CorsTest extends WebTestCase
{
    public function testAllowedOriginReceivesCompletePreflightHeaders(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/api/admin/dashboard', server: [
            'HTTP_ORIGIN' => 'http://localhost:4200',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PATCH',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, Content-Type',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:4200');
        self::assertResponseHeaderSame('Access-Control-Allow-Credentials', 'true');
        self::assertStringContainsString('PATCH', (string) $client->getResponse()->headers->get('Access-Control-Allow-Methods'));
        $allowedHeaders = strtolower((string) $client->getResponse()->headers->get('Access-Control-Allow-Headers'));
        self::assertStringContainsString('authorization', $allowedHeaders);
        self::assertStringContainsString('content-type', $allowedHeaders);
    }

    public function testUnknownOriginIsNotReflected(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/api/admin/dashboard', server: [
            'HTTP_ORIGIN' => 'https://attacker.invalid',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'DELETE',
        ]);

        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }
}
