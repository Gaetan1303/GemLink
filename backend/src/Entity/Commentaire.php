<?php



namespace App\Entity;

use App\Repository\CommentaireRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.4 — Commentaires MVP.
 */
#[ORM\Entity(repositoryClass: CommentaireRepository::class)]
#[ORM\Table(name: 'commentaire')]
class Commentaire
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Publication $publication;

    #[ORM\Column(type: 'text')]
    private string $content = '';

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    public function __construct(User $user, Publication $publication, string $content)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->publication = $publication;
        $this->content = $content;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPublication(): Publication
    {
        return $this->publication;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * CA-1 : la validation de longueur (1000 caractères) est faite en amont
     * par CommentService — l'entité reste une source de vérité "propre"
     * (même répartition des responsabilités que Publication::setTitle).
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * CA-2 : un commentaire soft-deleted n'est plus affiché parmi les
     * commentaires actifs d'un post.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
