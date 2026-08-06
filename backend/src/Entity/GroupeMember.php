<?php

namespace App\Entity;

use App\Repository\GroupeMemberRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GroupeMemberRepository::class)]
#[ORM\Table(name: 'groupe_member')]
#[ORM\Index(name: 'idx_groupe_member_group_status', fields: ['group', 'status'])]
class GroupeMember
{
    public const OWNER = 'OWNER'; public const OFFICER = 'OFFICER'; public const MEMBER = 'MEMBER';
    public const ACTIVE = 'ACTIVE'; public const LEFT = 'LEFT'; public const REMOVED = 'REMOVED';

    #[ORM\Id, ORM\Column(type: 'uuid')] private Uuid $id;
    #[ORM\ManyToOne(targetEntity: Groupe::class), ORM\JoinColumn(name: 'groupe_id', nullable: false, onDelete: 'CASCADE')] private Groupe $group;
    #[ORM\ManyToOne(targetEntity: User::class), ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')] private User $user;
    #[ORM\Column(length: 20)] private string $role;
    #[ORM\Column(length: 20)] private string $status = self::ACTIVE;
    #[ORM\Column(name: 'joined_at', type: 'datetimetz_immutable')] private DateTimeImmutable $joinedAt;
    #[ORM\Column(name: 'left_at', type: 'datetimetz_immutable', nullable: true)] private ?DateTimeImmutable $leftAt = null;
    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')] private DateTimeImmutable $createdAt;

    public function __construct(Groupe $group, User $user, string $role = self::MEMBER)
    { $this->id = Uuid::v7(); $this->group = $group; $this->user = $user; $this->joinedAt = $this->createdAt = new DateTimeImmutable(); $this->changeRole($role); }
    public function getId(): Uuid { return $this->id; } public function getGroup(): Groupe { return $this->group; }
    public function getUser(): User { return $this->user; } public function getRole(): string { return $this->role; }
    public function getStatus(): string { return $this->status; } public function getJoinedAt(): DateTimeImmutable { return $this->joinedAt; }
    public function getLeftAt(): ?DateTimeImmutable { return $this->leftAt; } public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function isActive(): bool { return $this->status === self::ACTIVE; } public function isOwner(): bool { return $this->role === self::OWNER; }
    public function changeRole(string $role): self { if (!in_array($role, [self::OWNER,self::OFFICER,self::MEMBER], true)) throw new InvalidArgumentException('Rôle de faction invalide.'); $this->role = $role; return $this; }
    public function leave(string $status = self::LEFT): self { if (!$this->isActive()) throw new LogicException('Cette adhésion est déjà terminée.'); if (!in_array($status,[self::LEFT,self::REMOVED],true)) throw new InvalidArgumentException('Statut de départ invalide.'); $this->status=$status; $this->leftAt=new DateTimeImmutable(); return $this; }
}
