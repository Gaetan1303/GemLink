<?php

namespace App\Entity;

use App\Repository\GroupeRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GroupeRepository::class)]
#[ORM\Table(name: 'groupe')]
#[ORM\UniqueConstraint(name: 'uq_groupe_slug', fields: ['slug'])]
class Groupe
{
    public const VISIBILITY_PUBLIC = 'PUBLIC';
    public const VISIBILITY_PRIVATE = 'PRIVATE';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    #[ORM\Id] #[ORM\Column(type: 'uuid', unique: true)] private Uuid $id;
    #[ORM\Column(length: 100)] private string $name;
    #[ORM\Column(length: 120, unique: true)] private string $slug;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $description = null;
    #[ORM\Column(length: 20)] private string $visibility;
    #[ORM\Column(length: 20)] private string $status = self::STATUS_ACTIVE;
    #[ORM\Column(name: 'avatar_url', type: 'text', nullable: true)] private ?string $avatarUrl = null;
    #[ORM\Column(name: 'banner_url', type: 'text', nullable: true)] private ?string $bannerUrl = null;
    #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(name: 'created_by', nullable: false, onDelete: 'CASCADE')] private User $createdBy;
    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')] private DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')] private DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $slug, User $creator, string $visibility = self::VISIBILITY_PUBLIC)
    {
        $this->id = Uuid::v7(); $this->createdBy = $creator; $this->createdAt = $this->updatedAt = new DateTimeImmutable();
        $this->rename($name); $this->slug = $slug; $this->setVisibility($visibility);
    }
    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSlug(): string { return $this->slug; }
    public function getDescription(): ?string { return $this->description; }
    public function getVisibility(): string { return $this->visibility; }
    public function getStatus(): string { return $this->status; }
    public function getAvatarUrl(): ?string { return $this->avatarUrl; }
    public function getBannerUrl(): ?string { return $this->bannerUrl; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
    public function rename(string $name): self { $name = trim($name); if (mb_strlen($name) < 3 || mb_strlen($name) > 100) throw new \InvalidArgumentException('Le nom de la faction doit contenir entre 3 et 100 caractères.'); $this->name = $name; return $this->touch(); }
    public function setDescription(?string $description): self { $description = $description === null ? null : trim($description); if ($description !== null && mb_strlen($description) > 2000) throw new \InvalidArgumentException('La description est limitée à 2000 caractères.'); $this->description = $description ?: null; return $this->touch(); }
    public function setVisibility(string $visibility): self { if (!in_array($visibility, [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE], true)) throw new \InvalidArgumentException('Visibilité de faction invalide.'); $this->visibility = $visibility; return $this->touch(); }
    public function setMedia(?string $avatarUrl, ?string $bannerUrl): self { $this->avatarUrl = $avatarUrl; $this->bannerUrl = $bannerUrl; return $this->touch(); }
    public function archive(): self { $this->status = self::STATUS_ARCHIVED; return $this->touch(); }
    private function touch(): self { $this->updatedAt = new DateTimeImmutable(); return $this; }
}
