<?php



namespace App\Entity;

use App\Repository\PublicationRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PublicationRepository::class)]
#[ORM\Table(name: 'publication')]
class Publication
{
    // ── Types de média (CA-2) ───────────────────────────────────
    public const MEDIA_TYPE_IMAGE = 'IMAGE';
    public const MEDIA_TYPE_VIDEO = 'VIDEO';

    public const MEDIA_TYPES = [self::MEDIA_TYPE_IMAGE, self::MEDIA_TYPE_VIDEO];

    // ── Statuts du cycle de vie (CA-3) ───────────────────────────
    public const STATUS_PENDING_ANALYSIS = 'PENDING_ANALYSIS';
    public const STATUS_ANALYZED = 'ANALYZED';
    public const STATUS_ANALYSIS_FAILED = 'ANALYSIS_FAILED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'media_url', type: 'text')]
    private string $mediaUrl = '';

    #[ORM\Column(name: 'media_type', length: 20)]
    private string $mediaType = self::MEDIA_TYPE_IMAGE;

    #[ORM\Column(name: 'status', length: 40)]
    private string $status = self::STATUS_PENDING_ANALYSIS;

    #[ORM\Column(name: 'is_sponsored')]
    private bool $isSponsored = false;

    #[ORM\Column(name: 'view_count', type: 'integer', options: ['default' => 0])]
    private int $viewCount = 0;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'publication_tag')]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $tags;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    public function __construct(User $user, string $mediaUrl, string $mediaType = self::MEDIA_TYPE_IMAGE)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->mediaUrl = $mediaUrl;
        $this->mediaType = $mediaType;
        $this->createdAt = new DateTimeImmutable();
        $this->tags = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        $this->touch();

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        $this->touch();

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

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * CA-3 : transition de statut pilotée par PostService / le handler d'analyse IA.
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function isSponsored(): bool
    {
        return $this->isSponsored;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    /**
     * US 2.2 — incrémenté à chaque consultation du détail d'un post (best-effort,
     * pas de déduplication par utilisateur/IP au stade MVP).
     */
    public function incrementViewCount(): self
    {
        ++$this->viewCount;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $this->touch();
        }

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        if ($this->tags->removeElement($tag)) {
            $this->touch();
        }

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * CA-4 : un post soft-deleted n'est plus visible/administrable comme un post actif.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
