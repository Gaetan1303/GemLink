<?php

namespace App\Tests\Controller;

use App\Controller\NewsletterController;
use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Le contrôleur est testé sans PostgreSQL : ses collaborations sont mockées. */
final class NewsletterControllerTest extends TestCase
{
    private EntityManagerInterface $em;
    private NewsletterSubscriberRepository $repository;
    /** @var array<string, NewsletterSubscriber> */
    private array $subscribers = [];

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(NewsletterSubscriberRepository::class);
        $this->repository->method('findByEmail')->willReturnCallback(fn (string $email): ?NewsletterSubscriber => $this->subscribers[$email] ?? null);
        $this->em->method('persist')->willReturnCallback(function (object $subscriber): void {
            if ($subscriber instanceof NewsletterSubscriber) $this->subscribers[$subscriber->getEmail()] = $subscriber;
        });
    }

    public function testSubscribeWithNewEmailReturns201(): void
    {
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $response = $this->controller()->subscribe($this->request('/newsletter/subscribe', ['email' => 'test@gem-link.org']));
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame('ACTIVE', $this->subscribers['test@gem-link.org']->getStatus());
    }

    public function testSubscribeWithInvalidEmailReturns400(): void
    {
        $this->em->expects($this->never())->method('flush');
        self::assertSame(Response::HTTP_BAD_REQUEST, $this->controller()->subscribe($this->request('/newsletter/subscribe', ['email' => 'pas-un-email']))->getStatusCode());
    }

    public function testSubscribeWithEmptyEmailReturns400(): void
    {
        self::assertSame(Response::HTTP_BAD_REQUEST, $this->controller()->subscribe($this->request('/newsletter/subscribe', ['email' => '']))->getStatusCode());
    }

    public function testSubscribeWithAlreadyActiveEmailReturns409(): void
    {
        $this->subscribers['existing@gem-link.org'] = (new NewsletterSubscriber())->setEmail('existing@gem-link.org');
        $this->em->expects($this->never())->method('flush');
        self::assertSame(Response::HTTP_CONFLICT, $this->controller()->subscribe($this->request('/newsletter/subscribe', ['email' => 'existing@gem-link.org']))->getStatusCode());
    }

    public function testSubscribeWithUnsubscribedEmailResubscribes(): void
    {
        $subscriber = (new NewsletterSubscriber())->setEmail('resub@gem-link.org')->unsubscribe();
        $this->subscribers[$subscriber->getEmail()] = $subscriber;
        $this->em->expects($this->once())->method('flush');
        self::assertSame(Response::HTTP_OK, $this->controller()->subscribe($this->request('/newsletter/subscribe', ['email' => 'resub@gem-link.org']))->getStatusCode());
        self::assertSame('ACTIVE', $subscriber->getStatus());
    }

    public function testUnsubscribeWithExistingEmailReturns200(): void
    {
        $subscriber = (new NewsletterSubscriber())->setEmail('toremove@gem-link.org');
        $this->subscribers[$subscriber->getEmail()] = $subscriber;
        $this->em->expects($this->once())->method('flush');
        self::assertSame(Response::HTTP_OK, $this->controller()->unsubscribe($this->request('/newsletter/unsubscribe', ['email' => 'toremove@gem-link.org']))->getStatusCode());
        self::assertSame('UNSUBSCRIBED', $subscriber->getStatus());
    }

    public function testUnsubscribeWithUnknownEmailReturns404(): void
    {
        $this->em->expects($this->never())->method('flush');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->controller()->unsubscribe($this->request('/newsletter/unsubscribe', ['email' => 'inconnu@gem-link.org']))->getStatusCode());
    }

    private function controller(): NewsletterController
    {
        $controller = new NewsletterController($this->em, $this->repository);
        $controller->setContainer(new Container());
        return $controller;
    }
    private function request(string $path, array $data): Request { return Request::create($path, 'POST', [], [], [], [], json_encode($data, JSON_THROW_ON_ERROR)); }
}
