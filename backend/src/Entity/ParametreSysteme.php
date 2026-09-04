<?php

namespace App\Entity;

use App\Repository\ParametreSystemeRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Paramètre système configurable par l'Admin, stocké en clé/valeur.
 * Porte notamment le seuil de consensus (US 2.7 - CA-4) et le seuil
 * Trust Score du dataset candidat (US 2.7 - CA-5). Seule table réellement
 * nouvelle apportée par cette US : validation existait déjà depuis le
 * schéma initial du projet.
 */
#[ORM\Entity(repositoryClass: ParametreSystemeRepository::class)]
#[ORM\Table(name: 'parametre_systeme')]
#[ORM\UniqueConstraint(name: 'uq_parametre_systeme_cle', fields: ['cle'])]
class ParametreSysteme
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $cle;

    #[ORM\Column(length: 255)]
    private string $valeur;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $cle, string $valeur)
    {
        $this->id = Uuid::v7();
        $this->cle = $cle;
        $this->valeur = $valeur;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCle(): string
    {
        return $this->cle;
    }

    public function getValeur(): string
    {
        return $this->valeur;
    }

    public function setValeur(string $valeur): self
    {
        $this->valeur = $valeur;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
