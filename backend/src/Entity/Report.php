<?php



namespace App\Entity;

use App\Repository\ReportRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'report')]
class Report
{
    public const REASONS = ['INAPPROPRIATE_CONTENT', 'WRONG_IDENTIFICATION', 'SPAM', 'HARASSMENT'];
    public const STATUSES = ['PENDING', 'ACCEPTED', 'REJECTED'];
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Publication $publication;

    #[ORM\Column(name: 'reason_type', length: 50)]
    private string $reasonType = 'SPAM';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'status', length: 20)]
    private string $status = 'PENDING';

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, Publication $publication)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->publication = $publication;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getPublication(): Publication { return $this->publication; }
    public function getReasonType(): string { return $this->reasonType; }
    public function getDescription(): ?string { return $this->description; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }

    public function setReasonType(string $reasonType): self
    {
        if (!in_array($reasonType, self::REASONS, true)) throw new \InvalidArgumentException('Motif invalide.');
        $this->reasonType = $reasonType;
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function decide(string $status): self
    {
        if (!in_array($status, ['ACCEPTED', 'REJECTED'], true)) throw new \InvalidArgumentException('Décision invalide.');
        $this->status = $status;
        return $this;
    }
}
