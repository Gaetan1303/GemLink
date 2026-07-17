<?php

namespace App\Entity;

use App\Exception\VitrineValidationException;
use App\Repository\VitrineRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * US 4.1 — Vitrine : collection ordonnée de posts appartenant à un User.
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

    #[ORM\Column(length: 150)]
    private string $slug = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'view_count', type: 'integer', options: ['default' => 0])]
    private int $viewCount = 0;

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

    /**
     * CA-4 : ne fait AUCUNE vérification métier elle-même (pas d'accès aux
     * items depuis ici au-delà du count) — le garde-fou "vitrine vide" est
     * porté par VitrineService::publish(), pas par l'entité, pour rester
     * cohérent avec PostService qui porte déjà toute la validation métier.
     */
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

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function incrementViewCount(): self
    {
        ++$this->viewCount;

        return $this;
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

    public function getNextPosition(): int
    {
        if ($this->items->isEmpty()) {
            return 0;
        }

        $positions = array_map(
            static fn (VitrinePublication $item): int => $item->getPosition(),
            $this->items->toArray()
        );

        return max($positions) + 1;
    }

    /**
     * CA-3 : réordonne les items à partir d'une liste ordonnée d'IDs de
     * publication (pas d'ID propre à VitrinePublication : clé composite).
     *
     * @param string[] $orderedPublicationIds
     */
    public function reorderItems(array $orderedPublicationIds): void
    {
        $itemsByPublicationId = [];
        foreach ($this->items as $item) {
            $itemsByPublicationId[$item->getPublication()->getId()->toRfc4122()] = $item;
        }

        if (count($orderedPublicationIds) !== count($itemsByPublicationId)) {
            throw new VitrineValidationException('La liste fournie ne correspond pas au contenu de la Vitrine.');
        }

        foreach ($orderedPublicationIds as $index => $publicationId) {
            if (!isset($itemsByPublicationId[$publicationId])) {
                throw new VitrineValidationException(sprintf(
                    'Le post "%s" ne fait pas partie de cette Vitrine.',
                    $publicationId
                ));
            }

            $itemsByPublicationId[$publicationId]->setPosition($index);
            unset($itemsByPublicationId[$publicationId]);
        }

        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}