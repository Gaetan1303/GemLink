<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use App\Service\EmailService;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;
use App\Message\SendEmailMessage;

final class TestEmailControllerTest extends WebTestCase
{
    public function testSendEmailEndpointCallsEmailService(): void
    {
        $client = static::createClient();

       
        $busMock = $this->createMock(MessageBusInterface::class);
        $busMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendEmailMessage::class))
            ->willReturn(new Envelope(new SendEmailMessage('test@example.com', 'subject', 'emails/test.html.twig', [])));

        $client->getContainer()->set(MessageBusInterface::class, $busMock);

        $client->request('GET', '/test/email');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Email envoyé ! Vérifie ta boîte de réception.', $data['message']);
    }
}
