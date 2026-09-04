<?php

namespace App\Entity;

use App\Repository\PublicationPierreRepository;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * US 3.1 CA-4 : table de jointure Publication <-> Pierre porteuse de données
 * (confidence) — modélisée en entité dédiée plutôt qu'en ManyToMany nu, car
 * Doctrine ne permet pas de colonne supplémentaire sur une table de jointure
 * implicite. Correspond à PUBLICATION_PIERRE dans le MLD (doc/Diagramme.md).
 */
#[ORM\Entity(repositoryClass: PublicationPierreRepository::class)]
#[ORM\Table(name: 'publication_pierre')]
class PublicationPierre
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Publication::class)]
    // Pas de 'nullable' ici : ces colonnes font partie de la clé primaire
    // composite (#[ORM\Id]), donc Doctrine les force déjà à NOT NULL —
    // spécifier 'nullable' est un no-op déprécié depuis doctrine/orm et
    // deviendra une erreur bloquante en 4.0.
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Publication $publication;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Pierre::class)]
    #[ORM\JoinColumn(name: 'pierre_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Pierre $pierre;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4)]
    private string $confidence;

    public function __construct(Publication $publication, Pierre $pierre, float $confidence)
    {
        $this->publication = $publication;
        $this->pierre = $pierre;
        $this->setConfidence($confidence);
    }

    public function getPublication(): Publication
    {
        return $this->publication;
    }

    public function getPierre(): Pierre
    {
        return $this->pierre;
    }

    public function getConfidence(): float
    {
        return (float) $this->confidence;
    }

    public function setConfidence(float $confidence): self
    {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException('La confiance doit être comprise entre 0 et 1.');
        }

        $this->confidence = number_format($confidence, 4, '.', '');

        return $this;
    }

    /**
     * Seuil au-delà duquel une identification IA est jugée fiable sans
     * validation humaine. Centralisé (sérialisation) et
     * workflow de modération (US 4.x, VALIDATION) partagent la
     * même règle plutôt que de la dupliquer côté front ou controller.
     */
    public function isHighConfidence(float $threshold = 0.75): bool
    {
        return $this->getConfidence() >= $threshold;
    }
}