<?php

namespace App\Entity;

use App\Repository\PublicIdentificationRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Analyse publique temporaire : aucun historique ni lien utilisateur persistant. */
#[ORM\Entity(repositoryClass: PublicIdentificationRepository::class)]
#[ORM\Table(name: 'public_identification')]
class PublicIdentification
{
    public const STATUS_PENDING = 'PENDING_ANALYSIS';
    public const STATUS_ANALYZED = 'ANALYZED';
    public const STATUS_FAILED = 'ANALYSIS_FAILED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;
    #[ORM\Column(name: 'requester_key', length: 64)]
    private string $requesterKey;
    #[ORM\Column(name: 'media_url', type: 'text')]
    private string $mediaUrl;
    #[ORM\Column(name: 'mime_type', length: 100)]
    private string $mimeType;
    #[ORM\Column(length: 40)]
    private string $status = self::STATUS_PENDING;
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $result = null;
    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'expires_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $expiresAt;

    public function __construct(string $requesterKey, string $mediaUrl, string $mimeType)
    {
        $this->id = Uuid::v7(); $this->requesterKey = $requesterKey; $this->mediaUrl = $mediaUrl; $this->mimeType = $mimeType;
        $this->createdAt = new DateTimeImmutable(); $this->expiresAt = $this->createdAt->modify('+1 hour');
    }
    public function getId(): Uuid { return $this->id; }
    public function getRequesterKey(): string { return $this->requesterKey; }
    public function getMediaUrl(): string { return $this->mediaUrl; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getStatus(): string { return $this->status; }
    public function getResult(): ?array { return $this->result; }
    public function getExpiresAt(): DateTimeImmutable { return $this->expiresAt; }
    public function isExpired(): bool { return $this->expiresAt <= new DateTimeImmutable(); }
    public function markAnalyzed(array $result): void { $this->result = $result; $this->status = self::STATUS_ANALYZED; }
    public function markFailed(): void { $this->status = self::STATUS_FAILED; }
}
