<?php



namespace App\Entity;

use App\Repository\VitrinePublicationRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * US 4.1 CA-2/CA-3 : table de jointure Vitrine <-> Publication porteuse de
 * données (position) — entité dédiée pour la même raison que PublicationPierre :
 * Doctrine ne permet pas de colonne supplémentaire sur une table de jointure
 * implicite (ManyToMany nu).
 */
#[ORM\Entity(repositoryClass: VitrinePublicationRepository::class)]
#[ORM\Table(name: 'vitrine_publication')]
class VitrinePublication
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Vitrine::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'vitrine_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Vitrine $vitrine;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Publication $publication;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(name: 'added_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $addedAt;

    public function __construct(Vitrine $vitrine, Publication $publication, int $position)
    {
        $this->vitrine = $vitrine;
        $this->publication = $publication;
        $this->position = $position;
        $this->addedAt = new DateTimeImmutable();
    }

    public function getVitrine(): Vitrine
    {
        return $this->vitrine;
    }

    public function setVitrine(Vitrine $vitrine): self
    {
        $this->vitrine = $vitrine;

        return $this;
    }

    public function getPublication(): Publication
    {
        return $this->publication;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getAddedAt(): DateTimeImmutable
    {
        return $this->addedAt;
    }
}