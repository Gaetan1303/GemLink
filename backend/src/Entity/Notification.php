<?php



namespace App\Entity;

use App\Repository\NotificationRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Notification in-app générique (cible identifiée par targetType + targetId,
 * même pattern polymorphe que Report/AuditLog dans ce projet).
 *
 * US 2.4 CA-4 : type NEW_COMMENT, target = la publication commentée.
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
class Notification
{
    public const TYPE_NEW_COMMENT = 'NEW_COMMENT';
    public const TYPE_NEW_LIKE = 'NEW_LIKE';
    public const TYPE_LEVEL_UP = 'LEVEL_UP';
    public const TYPE_BADGE_AWARDED = 'BADGE_AWARDED';

    public const TARGET_TYPE_PUBLICATION = 'PUBLICATION';
    public const TARGET_TYPE_LEVEL = 'LEVEL';
    public const TARGET_TYPE_BADGE = 'BADGE';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** Utilisateur à l'origine de l'évènement, nécessaire à la déduplication des likes. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $actor = null;

    #[ORM\Column(length: 50)]
    private string $type = '';

    #[ORM\Column(name: 'target_id', type: 'uuid')]
    private ?Uuid $targetId = null;

    #[ORM\Column(name: 'target_type', length: 50)]
    private string $targetType = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(name: 'is_read')]
    private bool $isRead = false;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, string $type)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->type = $type;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getActor(): ?User { return $this->actor; }

    public function setActor(?User $actor): self { $this->actor = $actor; return $this; }

    public function getTargetId(): ?Uuid
    {
        return $this->targetId;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTarget(Uuid $targetId, string $targetType): self
    {
        $this->targetId = $targetId;
        $this->targetType = $targetType;

        return $this;
    }

    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): self { $this->content = $content; return $this; }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function markAsRead(): self
    {
        $this->isRead = true;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
