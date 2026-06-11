<?php

declare(strict_types=1);

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

    public function __construct(VersionModeleIa $versionModele, int $minTrustScore)
    {
        $this->id = Uuid::v7();
        $this->versionModele = $versionModele;
        $this->minTrustScore = $minTrustScore;
        $this->createdAt = new DateTimeImmutable();
    }
}
