<?php

namespace App\Entity;

use App\Repository\PierreRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PierreRepository::class)]
#[ORM\Table(name: 'pierre')]
class Pierre
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100, unique: true)]
    private string $name = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, nullable: true)]
    private ?string $hardness = null;

    #[ORM\Column(name: 'crystal_system', length: 50, nullable: true)]
    private ?string $crystalSystem = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $composition = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getHardness(): ?float
    {
        return $this->hardness !== null ? (float) $this->hardness : null;
    }

    public function setHardness(?float $hardness): self
    {
        $this->hardness = $hardness !== null ? (string) $hardness : null;

        return $this;
    }

    public function getCrystalSystem(): ?string
    {
        return $this->crystalSystem;
    }

    public function setCrystalSystem(?string $crystalSystem): self
    {
        $this->crystalSystem = $crystalSystem;

        return $this;
    }

    public function getComposition(): ?string
    {
        return $this->composition;
    }

    public function setComposition(?string $composition): self
    {
        $this->composition = $composition;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}