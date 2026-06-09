<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ValidationRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ValidationRepository::class)]
#[ORM\Table(name: 'validation')]
class Validation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Publication $publication;

    #[ORM\ManyToOne(targetEntity: Pierre::class)]
    #[ORM\JoinColumn(name: 'pierre_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Pierre $pierre;

    #[ORM\Column(name: 'action', length: 20)]
    private string $action = 'CONFIRM';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $proposedLabel = null;

    #[ORM\Column(name: 'trust_score_snapshot', type: 'smallint')]
    private int $trustScoreSnapshot = 0;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, Publication $publication, Pierre $pierre, int $trustScoreSnapshot)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->publication = $publication;
        $this->pierre = $pierre;
        $this->trustScoreSnapshot = $trustScoreSnapshot;
        $this->createdAt = new DateTimeImmutable();
    }
}
