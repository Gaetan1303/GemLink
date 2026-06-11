<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use App\Service\AuthService;
use App\Entity\User;

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
                return isset($data['email'], $data['username'], $data['password']);
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
                'password' => 'Password123!'
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Inscription réussie. Veuillez valider votre email.', $responseData['message']);
    }

}