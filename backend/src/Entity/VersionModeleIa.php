<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\VersionModeleIaRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VersionModeleIaRepository::class)]
#[ORM\Table(name: 'ai_model_version')]
class VersionModeleIa
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 50)]
    private string $name = '';

    #[ORM\Column(name: 'model_type', length: 20)]
    private string $modelType = '';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4, nullable: true)]
    private ?string $accuracy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'status', length: 20)]
    private string $status = 'TRAINING';

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->createdAt = new DateTimeImmutable();
    }
}
