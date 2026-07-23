<?php



namespace App\Tests\Entity;

use App\Entity\NewsletterSubscriber;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class NewsletterSubscriberTest extends TestCase
{
    public function testConstructorInitializesDefaults(): void
    {
        $subscriber = new NewsletterSubscriber();

        $this->assertInstanceOf(Uuid::class, $subscriber->getId());
        $this->assertSame('ACTIVE', $subscriber->getStatus());
        $this->assertNotNull($subscriber->getSubscribedAt());
        $this->assertNull($subscriber->getUnsubscribedAt());
    }

    public function testSetEmailNormalizesValue(): void
    {
        $subscriber = new NewsletterSubscriber();
        $subscriber->setEmail('  Test@GemLink.org  ');

        $this->assertSame('test@gemlink.org', $subscriber->getEmail());
    }

    public function testSetStatusAcceptsValidValues(): void
    {
        $subscriber = new NewsletterSubscriber();

        $subscriber->setStatus('UNSUBSCRIBED');
        $this->assertSame('UNSUBSCRIBED', $subscriber->getStatus());

        $subscriber->setStatus('ACTIVE');
        $this->assertSame('ACTIVE', $subscriber->getStatus());
    }

    public function testSetStatusRejectsInvalidValue(): void
    {
        $subscriber = new NewsletterSubscriber();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Statut newsletter invalide.');

        $subscriber->setStatus('INVALID_STATUS');
    }

    public function testUnsubscribeSetsStatusAndTimestamp(): void
    {
        $subscriber = new NewsletterSubscriber();
        $subscriber->unsubscribe();

        $this->assertSame('UNSUBSCRIBED', $subscriber->getStatus());
        $this->assertNotNull($subscriber->getUnsubscribedAt());
    }

    public function testResubscribeResetsStatusAndClearsTimestamp(): void
    {
        $subscriber = new NewsletterSubscriber();
        $subscriber->unsubscribe();
        $subscriber->resubscribe();

        $this->assertSame('ACTIVE', $subscriber->getStatus());
        $this->assertNull($subscriber->getUnsubscribedAt());
    }

    public function testFluentSettersReturnSelf(): void
    {
        $subscriber = new NewsletterSubscriber();

        $this->assertSame($subscriber, $subscriber->setEmail('test@gem-link.org'));
        $this->assertSame($subscriber, $subscriber->setStatus('ACTIVE'));
        $this->assertSame($subscriber, $subscriber->unsubscribe());
        $this->assertSame($subscriber, $subscriber->resubscribe());
    }
}