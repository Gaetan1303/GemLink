<?php



namespace App\Entity;

use App\Repository\AuditLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Journal d'audit immuable (cf. trigger_protect_audit_log en base : ni UPDATE
 * ni DELETE ne sont autorisés une fois une ligne insérée).
 *
 * US 2.4 CA-2 : action COMMENT_DELETED, target = le commentaire supprimé.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
class AuditLog
{
    public const ACTION_COMMENT_DELETED = 'COMMENT_DELETED';

    public const TARGET_TYPE_COMMENTAIRE = 'COMMENTAIRE';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(length: 50)]
    private string $action = '';

    #[ORM\Column(name: 'target_type', length: 50)]
    private string $targetType = '';

    #[ORM\Column(name: 'target_id', type: 'uuid')]
    private Uuid $targetId;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, string $action, string $targetType, Uuid $targetId)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->action = $action;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
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

    public function getAction(): string
    {
        return $this->action;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function getTargetId(): Uuid
    {
        return $this->targetId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
