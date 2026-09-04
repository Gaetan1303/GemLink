<?php



namespace App\Entity;

use App\Repository\VitrineRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * US 4.1 — Vitrine : collection ordonnée de posts existants ET de médias
 * uploadés directement, appartenant à un User. Les deux types de contenu
 * partagent un même espace de positions pour un glisser-déposer unifié.
 *
 * US 4.2 — Ajout de qrCodeUrl (CA-3). Le compteur de vues (viewCount) reste
 * mis à jour en base par lots via VitrineRepository::incrementViewCount(),
 * appelé par le worker de flush périodique (CA-2) — pas par
 * incrementViewCount() de cette entité, qui reste disponible pour un usage
 * synchrone ponctuel (ex: back-office) mais n'est plus utilisée par la page
 * publique.
 */
#[ORM\Entity(repositoryClass: VitrineRepository::class)]
#[ORM\Table(name: 'vitrine')]
class Vitrine
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 100)]
    private string $title = '';

    #[ORM\Column(length: 150, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'view_count', type: 'integer', options: ['default' => 0])]
    private int $viewCount = 0;

    /**
     * US 4.2 - CA-3 : URL du QR code PNG stocké sur le CDN, pointant vers
     * l'URL publique de la Vitrine. Généré à la création
     * (cf. VitrineService::createVitrine() -> VitrineQrCodeService).
     */
    #[ORM\Column(name: 'qr_code_url', length: 500, nullable: true)]
    private ?string $qrCodeUrl = null;

    /**
     * Post généré automatiquement dans le feed principal lors de la
     * publication de cette Vitrine (cf. VitrineService::publish()).
     */
    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Publication $generatedPost = null;

    /**
     * @var Collection<int, VitrinePublication>
     */
    #[ORM\OneToMany(
        mappedBy: 'vitrine',
        targetEntity: VitrinePublication::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    /**
     * @var Collection<int, VitrineMedia>
     */
    #[ORM\OneToMany(
        mappedBy: 'vitrine',
        targetEntity: VitrineMedia::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $mediaItems;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(User $user, string $title, string $slug)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->title = $title;
        $this->slug = $slug;
        $this->items = new ArrayCollection();
        $this->mediaItems = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->touch();

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function publish(): void
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->touch();
    }

    public function unpublish(): void
    {
        $this->status = self::STATUS_DRAFT;
        $this->touch();
    }

    public function getGeneratedPost(): ?Publication
    {
        return $this->generatedPost;
    }

    public function setGeneratedPost(?Publication $generatedPost): self
    {
        $this->generatedPost = $generatedPost;

        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function incrementViewCount(): self
    {
        ++$this->viewCount;

        return $this;
    }

    public function getQrCodeUrl(): ?string
    {
        return $this->qrCodeUrl;
    }

    public function setQrCodeUrl(?string $qrCodeUrl): self
    {
        $this->qrCodeUrl = $qrCodeUrl;

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty() && $this->mediaItems->isEmpty();
    }

    public function getItemsCount(): int
    {
        return $this->items->count() + $this->mediaItems->count();
    }

    /**
     * @return Collection<int, VitrinePublication>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(VitrinePublication $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setVitrine($this);
            $this->touch();
        }

        return $this;
    }

    public function removeItem(VitrinePublication $item): self
    {
        if ($this->items->removeElement($item)) {
            $this->touch();
        }

        return $this;
    }

    /**
     * @return Collection<int, VitrineMedia>
     */
    public function getMediaItems(): Collection
    {
        return $this->mediaItems;
    }

    public function addMedia(VitrineMedia $media): self
    {
        if (!$this->mediaItems->contains($media)) {
            $this->mediaItems->add($media);
            $media->setVitrine($this);
            $this->touch();
        }

        return $this;
    }

    public function removeMedia(VitrineMedia $media): self
    {
        if ($this->mediaItems->removeElement($media)) {
            $this->touch();
        }

        return $this;
    }

    public function getNextPosition(): int
    {
        $positions = array_merge(
            array_map(static fn (VitrinePublication $i): int => $i->getPosition(), $this->items->toArray()),
            array_map(static fn (VitrineMedia $i): int => $i->getPosition(), $this->mediaItems->toArray()),
        );

        return $positions === [] ? 0 : max($positions) + 1;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
