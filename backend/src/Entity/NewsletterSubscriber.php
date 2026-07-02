<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsletterSubscriberRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(name: 'newsletter_subscriber')]
#[ORM\UniqueConstraint(name: 'uq_newsletter_subscriber_email', fields: ['email'])]
#[ORM\Index(name: 'idx_newsletter_subscriber_status', fields: ['status'])]
class NewsletterSubscriber
{
    private const STATUTS_AUTORISES = ['ACTIVE', 'UNSUBSCRIBED'];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $email = '';

    #[ORM\Column(length: 20, options: ['default' => 'ACTIVE'])]
    private string $status = 'ACTIVE';

    #[ORM\Column(name: 'subscribed_at')]
    private DateTimeImmutable $subscribedAt;

    #[ORM\Column(name: 'unsubscribed_at', nullable: true)]
    private ?DateTimeImmutable $unsubscribedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->subscribedAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::STATUTS_AUTORISES, true)) {
            throw new \InvalidArgumentException('Statut newsletter invalide.');
        }

        $this->status = $status;

        return $this;
    }

    public function getSubscribedAt(): DateTimeImmutable
    {
        return $this->subscribedAt;
    }

    public function getUnsubscribedAt(): ?DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function unsubscribe(): self
    {
        $this->status = 'UNSUBSCRIBED';
        $this->unsubscribedAt = new DateTimeImmutable();

        return $this;
    }

    public function resubscribe(): self
    {
        $this->status = 'ACTIVE';
        $this->unsubscribedAt = null;

        return $this;
    }
}