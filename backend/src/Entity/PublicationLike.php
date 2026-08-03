<?php

namespace App\Entity;

use App\Repository\PublicationLikeRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Un like est intentionnellement une entité : sa clé métier est le couple post/utilisateur. */
#[ORM\Entity(repositoryClass: PublicationLikeRepository::class)]
#[ORM\Table(name: 'publication_like')]
#[ORM\UniqueConstraint(name: 'uq_publication_like_publication_user', fields: ['publication', 'user'])]
class PublicationLike
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Publication $publication;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(Publication $publication, User $user)
    {
        $this->publication = $publication;
        $this->user = $user;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getPublication(): Publication { return $this->publication; }
    public function getUser(): User { return $this->user; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
