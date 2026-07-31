<?php



namespace App\Entity;

use App\Repository\JobFineTuningRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: JobFineTuningRepository::class)]
#[ORM\Table(name: 'job_fine_tuning')]
class JobFineTuning
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: VersionModeleIa::class)]
    #[ORM\JoinColumn(name: 'version_modele_ia_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private VersionModeleIa $versionModele;

    #[ORM\Column(name: 'min_trust_score', type: 'smallint')]
    private int $minTrustScore = 0;

    #[ORM\Column(name: 'status', length: 20)]
    private string $status = 'PENDING';

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'completed_at', type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $progress = 0;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    public function __construct(VersionModeleIa $versionModele, int $minTrustScore)
    {
        $this->id = Uuid::v7();
        $this->versionModele = $versionModele;
        $this->minTrustScore = $minTrustScore;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getVersionModele(): VersionModeleIa { return $this->versionModele; }
    public function getMinTrustScore(): int { return $this->minTrustScore; }
    public function getStatus(): string { return $this->status; }
    public function getProgress(): int { return $this->progress; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getStartedAt(): ?DateTimeImmutable { return $this->startedAt; }
    public function getCompletedAt(): ?DateTimeImmutable { return $this->completedAt; }
    public function start(): self { $this->status = 'RUNNING'; $this->progress = max(1, $this->progress); $this->startedAt ??= new DateTimeImmutable(); return $this; }
    public function setProgress(int $progress): self { $this->progress = max(0, min(100, $progress)); return $this; }
    public function complete(): self { $this->status = 'COMPLETED'; $this->progress = 100; $this->completedAt = new DateTimeImmutable(); return $this; }
    public function fail(string $message): self { $this->status = 'FAILED'; $this->errorMessage = mb_substr($message, 0, 1000); $this->completedAt = new DateTimeImmutable(); return $this; }
}
