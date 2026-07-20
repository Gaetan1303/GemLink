<?php



namespace App\Entity;

use App\Repository\VitrineMediaRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VitrineMediaRepository::class)]
#[ORM\Table(name: 'vitrine_media')]
class VitrineMedia
{
    public const MEDIA_TYPE_IMAGE = 'IMAGE';
    public const MEDIA_TYPE_VIDEO = 'VIDEO';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Vitrine::class, inversedBy: 'mediaItems')]
    #[ORM\JoinColumn(name: 'vitrine_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Vitrine $vitrine;

    #[ORM\Column(name: 'media_url', type: 'text')]
    private string $mediaUrl;

    #[ORM\Column(name: 'media_type', length: 20)]
    private string $mediaType;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(Vitrine $vitrine, string $mediaUrl, string $mediaType, int $position)
    {
        $this->id = Uuid::v7();
        $this->vitrine = $vitrine;
        $this->mediaUrl = $mediaUrl;
        $this->mediaType = $mediaType;
        $this->position = $position;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getVitrine(): Vitrine
    {
        return $this->vitrine;
    }

    public function setVitrine(Vitrine $vitrine): self
    {
        $this->vitrine = $vitrine;

        return $this;
    }

    public function getMediaUrl(): string
    {
        return $this->mediaUrl;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}