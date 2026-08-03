<?php



namespace App\Entity;

use App\Repository\ValidationRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.7 : validation communautaire d'une identification IA.
 *
 * Une seule ligne par couple (publication, user) — contrainte
 * uq_validation_pub_user — donc une resoumission MET À JOUR la ligne
 * existante plutôt que d'en créer une nouvelle. CA-2 (traçabilité
 * historique) est garanti autrement qu'en gardant tout l'historique brut :
 * trust_score_snapshot reste figé à la valeur du moment de la soumission
 * jusqu'à ce que l'utilisateur resoumette, auquel cas un nouveau snapshot
 * est pris pour cette resoumission-là.
 *
 * L'INSERT/UPDATE sur cette table déclenche le trigger PostgreSQL
 * update_trust_score_after_validation, qui recalcule le trust_score de
 * l'AUTEUR du post (ratio CONFIRM/total sur ses publications) — un
 * mécanisme de réputation distinct de la pondération CA-3, qui porte sur
 * le Trust Score du VALIDATEUR. Aucune logique PHP à dupliquer ici.
 */
#[ORM\Entity(repositoryClass: ValidationRepository::class)]
#[ORM\Table(name: 'validation')]
#[ORM\UniqueConstraint(name: 'uq_validation_pub_user', columns: ['publication_id', 'user_id'])]
class Validation
{
    public const ACTION_CONFIRM = 'CONFIRM';
    public const ACTION_CORRECT = 'CORRECT';
    public const ACTION_REJECT = 'REJECT';

    public const ACTIONS = [self::ACTION_CONFIRM, self::ACTION_CORRECT, self::ACTION_REJECT];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Publication::class)]
    #[ORM\JoinColumn(name: 'publication_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Publication $publication;

    /**
     * Label IA en cours au moment de la validation (CONFIRM/REJECT portent
     * directement sur cette pierre ; CORRECT la conserve comme référence
     * du label qu'on corrige, avec proposedLabel comme alternative).
     */
    #[ORM\ManyToOne(targetEntity: Pierre::class)]
    #[ORM\JoinColumn(name: 'pierre_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Pierre $pierre;

    #[ORM\Column(name: 'action', length: 20)]
    private string $action = self::ACTION_CONFIRM;

    /**
     * Label alternatif en texte libre (CA-1) : l'autocomplétion sur le
     * catalogue Stone est une aide de saisie côté front, mais la valeur
     * stockée n'est pas forcément un Pierre existant. Résolution vers un
     * Pierre du catalogue via PierreRepository::findOneByNameIgnoreCase()
     * au moment du calcul de consensus, pas à la soumission.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $proposedLabel = null;

    /**
     * Snapshot du Trust Score du validateur au moment de la soumission
     * (CA-2, CA-3). Repris à chaque resoumission de cette même ligne.
     */
    #[ORM\Column(name: 'trust_score_snapshot', type: 'smallint')]
    private int $trustScoreSnapshot = 0;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, Publication $publication, Pierre $pierre, int $trustScoreSnapshot)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->publication = $publication;
        $this->pierre = $pierre;
        $this->trustScoreSnapshot = $trustScoreSnapshot;
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

    public function getPierre(): Pierre
    {
        return $this->pierre;
    }

    public function setPierre(Pierre $pierre): self
    {
        $this->pierre = $pierre;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('Action de validation invalide.');
        }

        $this->action = $action;

        if ($action !== self::ACTION_CORRECT) {
            $this->proposedLabel = null;
        }

        return $this;
    }

    public function getProposedLabel(): ?string
    {
        return $this->proposedLabel;
    }

    public function setProposedLabel(?string $proposedLabel): self
    {
        $this->proposedLabel = $proposedLabel;

        return $this;
    }

    public function getTrustScoreSnapshot(): int
    {
        return $this->trustScoreSnapshot;
    }

    public function setTrustScoreSnapshot(int $trustScoreSnapshot): self
    {
        $this->trustScoreSnapshot = $trustScoreSnapshot;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
