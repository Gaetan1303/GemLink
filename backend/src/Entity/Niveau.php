<?php

namespace App\Entity;

use App\Repository\NiveauRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** A configurable points threshold for one user level. */
#[ORM\Entity(repositoryClass: NiveauRepository::class)]
#[ORM\Table(name: 'niveau')]
#[ORM\UniqueConstraint(name: 'uq_niveau_number', fields: ['number'])]
#[ORM\UniqueConstraint(name: 'uq_niveau_min_points', fields: ['minPoints'])]
class Niveau
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'number', type: 'smallint')]
    private int $number;

    #[ORM\Column(length: 50)]
    private string $name;

    #[ORM\Column(name: 'min_points')]
    private int $minPoints;

    #[ORM\ManyToOne(targetEntity: Badge::class)]
    #[ORM\JoinColumn(name: 'badge_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Badge $badge = null;

    public function __construct(int $number, string $name, int $minPoints)
    {
        $this->id = Uuid::v7();
        $this->number = $number;
        $this->name = trim($name);
        $this->minPoints = $minPoints;
    }

    public function getId(): Uuid { return $this->id; }
    public function getNumber(): int { return $this->number; }
    public function getName(): string { return $this->name; }
    public function getMinPoints(): int { return $this->minPoints; }
    public function getBadge(): ?Badge { return $this->badge; }

    public function setName(string $name): self { $this->name = trim($name); return $this; }
    public function setMinPoints(int $minPoints): self { $this->minPoints = $minPoints; return $this; }
    public function setBadge(?Badge $badge): self { $this->badge = $badge; return $this; }
}
