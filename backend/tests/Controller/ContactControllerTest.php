<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;

final class ContactControllerTest extends WebTestCase
{
    public function testSubmitWithValidDataReturns201(): void
    {
        $client = static::createClient();

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->once())->method('send');

        $client->getContainer()->set(MailerInterface::class, $mailerMock);

        $client->request(
            'POST',
            '/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nom'     => 'Billy',
                'email'   => 'billy@gem-link.org',
                'sujet'   => 'Test',
                'message' => 'Ceci est un message de test.',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Message envoyé avec succès.', $data['message']);
    }

    public function testSubmitWithMissingFieldsReturns400(): void
    {
        $client = static::createClient();

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->never())->method('send');

        $client->getContainer()->set(MailerInterface::class, $mailerMock);

        $client->request(
            'POST',
            '/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nom'     => 'Billy',
                'email'   => '',
                'message' => '',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Champs obligatoires manquants.', $data['message']);
    }

    public function testSubmitWithInvalidEmailReturns400(): void
    {
        $client = static::createClient();

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->never())->method('send');

        $client->getContainer()->set(MailerInterface::class, $mailerMock);

        $client->request(
            'POST',
            '/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nom'     => 'Billy',
                'email'   => 'pas-un-email',
                'message' => 'Un message valide.',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Adresse email invalide.', $data['message']);
    }

    public function testSubmitWithoutSubjectUsesDefault(): void
    {
        $client = static::createClient();

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->once())->method('send');

        $client->getContainer()->set(MailerInterface::class, $mailerMock);

        $client->request(
            'POST',
            '/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nom'     => 'Billy',
                'email'   => 'billy@gem-link.org',
                'message' => 'Message sans sujet.',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }
}