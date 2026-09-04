<?php


namespace App\Entity;

use App\Repository\VersionModeleIaRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VersionModeleIaRepository::class)]
#[ORM\Table(name: 'ai_model_version')]
class VersionModeleIa
{
    public const TYPE_YOLO = 'YOLO';
    public const TYPE_VIT = 'VIT';
    public const TYPE_CLIP = 'CLIP';

    public const STATUS_TRAINING = 'TRAINING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_DEPRECATED = 'DEPRECATED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 50, unique: true)]
    private string $name = '';

    #[ORM\Column(name: 'model_type', length: 20)]
    private string $modelType = '';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4, nullable: true)]
    private ?string $accuracy = null;

    #[ORM\Column(name: 'f1_score', type: 'decimal', precision: 5, scale: 4, nullable: true)]
    private ?string $f1Score = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'status', length: 20)]
    private string $status = self::STATUS_TRAINING;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $name, string $modelType, string $status = self::STATUS_TRAINING)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->modelType = $modelType;
        $this->status = $status;
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

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAccuracy(): ?float { return $this->accuracy === null ? null : (float) $this->accuracy; }
    public function getF1Score(): ?float { return $this->f1Score === null ? null : (float) $this->f1Score; }
    public function setMetrics(?float $accuracy, ?float $f1Score): self
    {
        if (($accuracy !== null && ($accuracy < 0 || $accuracy > 1)) || ($f1Score !== null && ($f1Score < 0 || $f1Score > 1))) {
            throw new \InvalidArgumentException('Les métriques doivent être comprises entre 0 et 1.');
        }
        $this->accuracy = $accuracy === null ? null : number_format($accuracy, 4, '.', '');
        $this->f1Score = $f1Score === null ? null : number_format($f1Score, 4, '.', '');
        return $this;
    }

    public function activate(): self { $this->status = self::STATUS_ACTIVE; return $this; }
    public function deprecate(): self { $this->status = self::STATUS_DEPRECATED; return $this; }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
