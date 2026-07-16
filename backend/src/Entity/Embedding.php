<?php


namespace App\Entity;

use App\Repository\EmbeddingRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Pgvector\Vector;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EmbeddingRepository::class)]
#[ORM\Table(name: 'embedding')]
class Embedding
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    // uq_embedding_publication en base : un seul embedding par publication.
    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE', unique: true)]
    private Publication $publication;

    #[ORM\ManyToOne(targetEntity: VersionModeleIa::class)]
    #[ORM\JoinColumn(name: 'version_modele_ia_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private VersionModeleIa $versionModele;

    // CORRECTIF : la colonne Doctrine type 'vector' (Pgvector\Doctrine\VectorType)
    // convertit vers/depuis un objet Pgvector\Vector, jamais une string brute.
    #[ORM\Column(name: 'vector_data', type: 'vector')]
    private Vector $vectorData;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * @param float[] $vectorData
     */
    public function __construct(Publication $publication, VersionModeleIa $versionModele, array $vectorData)
    {
        $this->id = Uuid::v7();
        $this->publication = $publication;
        $this->versionModele = $versionModele;
        $this->vectorData = new Vector($vectorData);
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPublication(): Publication
    {
        return $this->publication;
    }

    public function getVersionModele(): VersionModeleIa
    {
        return $this->versionModele;
    }

    public function getVectorData(): Vector
    {
        return $this->vectorData;
    }

    /**
     * @param float[] $vectorData
     */
    public function updateVectorData(array $vectorData, VersionModeleIa $versionModele): self
    {
        $this->vectorData = new Vector($vectorData);
        $this->versionModele = $versionModele;

        return $this;
    }
}