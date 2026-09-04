<?php



namespace App\Entity;

use App\Repository\BadgeRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BadgeRepository::class)]
#[ORM\Table(name: 'badge')]
class Badge
{
    public const CONDITION_POST_COUNT = 'POST_COUNT';
    public const CONDITION_VALIDATION_COUNT = 'VALIDATION_COUNT';
    public const CONDITION_STONE_IDENTIFICATION_COUNT = 'STONE_IDENTIFICATION_COUNT';
    public const CONDITION_MINERAL_IDENTIFICATION_COUNT = 'MINERAL_IDENTIFICATION_COUNT';
    public const CONDITION_LEVEL_REACHED = 'LEVEL_REACHED';

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

    #[ORM\ManyToOne(targetEntity: Pierre::class)]
    #[ORM\JoinColumn(name: 'pierre_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Pierre $pierre = null;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getConditionType(): string { return $this->conditionType; }
    public function getConditionValue(): int { return $this->conditionValue; }
    public function getPierre(): ?Pierre { return $this->pierre; }

    public function setDescription(?string $description): self { $this->description = $description === null ? null : trim($description); return $this; }
    public function setName(string $name): self { $this->name = trim($name); return $this; }
    public function setCondition(string $type, int $value): self { $this->conditionType = $type; $this->conditionValue = $value; return $this; }
    public function setPierre(?Pierre $pierre): self { $this->pierre = $pierre; return $this; }

    /** Required by the Prototype pattern: every cloned badge is a distinct persisted entity. */
    public function __clone()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
    }
}
