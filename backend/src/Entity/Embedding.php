<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmbeddingRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EmbeddingRepository::class)]
#[ORM\Table(name: 'embedding')]
class Embedding
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Publication $publication;

    #[ORM\ManyToOne(targetEntity: VersionModeleIa::class)]
    #[ORM\JoinColumn(name: 'version_modele_ia_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private VersionModeleIa $versionModele;

    #[ORM\Column(name: 'vector_data', type: 'vector')]
    private string $vectorData = '';

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(Publication $publication, VersionModeleIa $versionModele, string $vectorData)
    {
        $this->id = Uuid::v7();
        $this->publication = $publication;
        $this->versionModele = $versionModele;
        $this->vectorData = $vectorData;
        $this->createdAt = new DateTimeImmutable();
    }
}
