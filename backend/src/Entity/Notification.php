<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 50)]
    private string $type = '';

    #[ORM\Column(name: 'target_id', type: 'uuid')]
    private ?Uuid $targetId = null;

    #[ORM\Column(name: 'target_type', length: 50)]
    private string $targetType = '';

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
}
