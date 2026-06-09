<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BadgeRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BadgeRepository::class)]
#[ORM\Table(name: 'badge')]
class Badge
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'condition_type', length: 50)]
    private string $conditionType = '';

    #[ORM\Column(name: 'condition_value', type: 'integer')]
    private int $conditionValue = 0;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->createdAt = new DateTimeImmutable();
    }
}
