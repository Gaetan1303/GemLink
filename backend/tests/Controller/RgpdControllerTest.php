<?php



namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;

final class RgpdControllerTest extends WebTestCase
{
    public function testRequestWithValidDataReturns201(): void
    {
        $client = static::createClient();

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->once())->method('send');

        $client->getContainer()->set(MailerInterface::class, $mailerMock);

        $client->request(
            'POST',
            '/rgpd-request',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nom'     => 'Billy',
                'email'   => 'billy@gem-link.org',
                'message' => 'Je souhaite accéder à mes données personnelles.',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Votre demande a bien été envoyée.', $data['message']);
    }

    public function testRequestWithMissingFieldsReturns400(): void
    {
        $client = static::createClient();

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->never())->method('send');

        $client->getContainer()->set(MailerInterface::class, $mailerMock);

        $client->request(
            'POST',
            '/rgpd-request',
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

    public function testRequestWithInvalidEmailReturns400(): void
    {
        $client = static::createClient();

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->never())->method('send');

        $client->getContainer()->set(MailerInterface::class, $mailerMock);

        $client->request(
            'POST',
            '/rgpd-request',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nom'     => 'Billy',
                'email'   => 'pas-un-email',
                'message' => 'Demande de suppression.',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Adresse email invalide.', $data['message']);
    }

    public function testRequestWithEmptyBodyReturns400(): void
    {
        $client = static::createClient();

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects($this->never())->method('send');

        $client->getContainer()->set(MailerInterface::class, $mailerMock);

        $client->request(
            'POST',
            '/rgpd-request',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testOptionsRequestReturns204(): void
    {
        $client = static::createClient();

        $client->request('OPTIONS', '/rgpd-request');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}