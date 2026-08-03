<?php

namespace App\Entity;

use App\Repository\PointTransactionRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Immutable ledger entry used to explain a user's points balance. */
#[ORM\Entity(repositoryClass: PointTransactionRepository::class)]
#[ORM\Table(name: 'point_transaction')]
#[ORM\UniqueConstraint(name: 'uq_point_transaction_source', columns: ['user_id', 'action', 'source_id'])]
#[ORM\Index(name: 'idx_point_transaction_user_date', columns: ['user_id', 'created_at'])]
class PointTransaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 50)]
    private string $action;

    #[ORM\Column(type: 'smallint')]
    private int $amount;

    #[ORM\Column(name: 'source_id', type: 'uuid')]
    private Uuid $sourceId;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, string $action, int $amount, Uuid $sourceId)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->action = $action;
        $this->amount = $amount;
        $this->sourceId = $sourceId;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getAction(): string { return $this->action; }
    public function getAmount(): int { return $this->amount; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
