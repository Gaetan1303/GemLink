<?php

namespace App\Tests\Controller;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class NewsletterControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Nettoyage avant chaque test
        $this->entityManager->createQuery('DELETE FROM App\Entity\NewsletterSubscriber')->execute();
    }

    public function testSubscribeWithNewEmailReturns201(): void
    {
        $this->client->request(
            'POST',
            '/newsletter/subscribe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test@gem-link.org'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Inscription confirmée.', $data['message']);

        $repository = static::getContainer()->get(NewsletterSubscriberRepository::class);
        $subscriber = $repository->findByEmail('test@gem-link.org');

        $this->assertNotNull($subscriber);
        $this->assertSame('ACTIVE', $subscriber->getStatus());
    }

    public function testSubscribeWithInvalidEmailReturns400(): void
    {
        $this->client->request(
            'POST',
            '/newsletter/subscribe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'pas-un-email'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Adresse email invalide.', $data['message']);
    }

    public function testSubscribeWithEmptyEmailReturns400(): void
    {
        $this->client->request(
            'POST',
            '/newsletter/subscribe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => ''])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testSubscribeWithAlreadyActiveEmailReturns409(): void
    {
        $subscriber = (new NewsletterSubscriber())->setEmail('existing@gem-link.org');
        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/newsletter/subscribe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'existing@gem-link.org'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Cette adresse est déjà inscrite à la newsletter.', $data['message']);
    }

    public function testSubscribeWithUnsubscribedEmailResubscribes(): void
    {
        $subscriber = (new NewsletterSubscriber())->setEmail('resub@gem-link.org');
        $subscriber->unsubscribe();
        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/newsletter/subscribe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'resub@gem-link.org'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Inscription confirmée.', $data['message']);

        $repository = static::getContainer()->get(NewsletterSubscriberRepository::class);
        $resubscribed = $repository->findByEmail('resub@gem-link.org');

        $this->assertSame('ACTIVE', $resubscribed->getStatus());
        $this->assertNull($resubscribed->getUnsubscribedAt());
    }

    public function testUnsubscribeWithExistingEmailReturns200(): void
    {
        $subscriber = (new NewsletterSubscriber())->setEmail('toremove@gem-link.org');
        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/newsletter/unsubscribe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'toremove@gem-link.org'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $repository = static::getContainer()->get(NewsletterSubscriberRepository::class);
        $unsubscribed = $repository->findByEmail('toremove@gem-link.org');

        $this->assertSame('UNSUBSCRIBED', $unsubscribed->getStatus());
        $this->assertNotNull($unsubscribed->getUnsubscribedAt());
    }

    public function testUnsubscribeWithUnknownEmailReturns404(): void
    {
        $this->client->request(
            'POST',
            '/newsletter/unsubscribe',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'inconnu@gem-link.org'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}