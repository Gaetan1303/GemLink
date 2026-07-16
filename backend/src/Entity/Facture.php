<?php



namespace App\Entity;

use App\Repository\FactureRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Vendeur;

#[ORM\Entity(repositoryClass: FactureRepository::class)]
#[ORM\Table(name: 'facture')]
class Facture
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Vendeur::class)]
    #[ORM\JoinColumn(name: 'vendeur_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Vendeur $vendeur;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(name: 'status', length: 20)]
    private string $status = 'PENDING';

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'paid_at', type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $paidAt = null;

    public function __construct(Vendeur $vendeur, string $amount)
    {
        $this->id = Uuid::v7();
        $this->vendeur = $vendeur;
        $this->amount = $amount;
        $this->createdAt = new DateTimeImmutable();
    }
}
