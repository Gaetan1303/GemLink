<?php



namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[ORM\UniqueConstraint(name: 'uq_utilisateur_email', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'uq_utilisateur_username', fields: ['username'])]
#[ORM\Index(name: 'idx_utilisateur_role', fields: ['role'])]
#[ORM\Index(name: 'idx_utilisateur_status', fields: ['status'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private const ROLES_AUTORISES = ['USER', 'EXPERT', 'MODERATOR', 'VENDEUR', 'ADMIN'];

    private const STATUTS_AUTORISES = ['PENDING_VALIDATION', 'ACTIVE', 'BANNED'];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 30)]
    private string $username = '';

    #[ORM\Column(length: 255)]
    private string $email = '';

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash = '';

    #[ORM\Column(name: 'avatar_url', type: 'text', nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(name: 'trust_score', type: 'smallint', options: ['default' => 0])]
    private int $trustScore = 0;

    #[ORM\Column(length: 20, options: ['default' => 'USER'], columnDefinition: 'user_role')]
    private string $role = 'USER';

    #[ORM\Column(options: ['default' => 0])]
    private int $points = 0;

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private int $level = 1;

    #[ORM\Column(length: 25, options: ['default' => 'PENDING_VALIDATION'], columnDefinition: 'user_status')]
    private string $status = 'PENDING_VALIDATION';
    
    #[ORM\Column(name: 'created_at')]
    private DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, RefreshToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RefreshToken::class, cascade: ['persist', 'remove'])]
    private Collection $refreshTokens;

    /**
     * @var Collection<int, PasswordResetToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: PasswordResetToken::class, cascade: ['persist', 'remove'])]
    private Collection $passwordResetTokens;

    /**
     * @var Collection<int, EmailValidationToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: EmailValidationToken::class, cascade: ['persist', 'remove'])]
    private Collection $emailValidationTokens;

    /** @var Collection<int, Badge> */
    #[ORM\ManyToMany(targetEntity: Badge::class)]
    #[ORM\JoinTable(name: 'user_badge')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'badge_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $badges;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
        $this->refreshTokens = new ArrayCollection();
        $this->passwordResetTokens = new ArrayCollection();
        $this->emailValidationTokens = new ArrayCollection();
        $this->badges = new ArrayCollection();
    }

    // --- Identifiants & Infos de Base ---

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = trim($username);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    // --- Sécurité & Rôles ---

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->role !== 'USER') {
            $roles[] = 'ROLE_' . $this->role;
        }

        return array_values(array_unique($roles));
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        if (!in_array($role, self::ROLES_AUTORISES, true)) {
            throw new InvalidArgumentException('Role utilisateur invalide.');
        }

        $this->role = $role;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function setPassword(string $password): self
    {
        $this->passwordHash = $password;

        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    // --- Profil & Métier ---

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): self
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;

        return $this;
    }

    public function getTrustScore(): int
    {
        return $this->trustScore;
    }

    public function setTrustScore(int $trustScore): self
    {
        $this->trustScore = max(0, min(100, $trustScore));

        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;

        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = max(1, $level);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::STATUTS_AUTORISES, true)) {
            throw new InvalidArgumentException('Statut utilisateur invalide.');
        }

        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Badge> */
    public function getBadges(): Collection { return $this->badges; }

    // --- Collections de Tokens (Relations) ---

    /**
     * @return Collection<int, RefreshToken>
     */
    public function getRefreshTokens(): Collection
    {
        return $this->refreshTokens;
    }

  


    /**
     * @return Collection<int, PasswordResetToken>
     */
    public function getPasswordResetTokens(): Collection
    {
        return $this->passwordResetTokens;
    }

    public function addPasswordResetToken(PasswordResetToken $token): self
    {
        if (!$this->passwordResetTokens->contains($token)) {
            $this->passwordResetTokens->add($token);
            $token->setUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, EmailValidationToken>
     */
    public function getEmailValidationTokens(): Collection
    {
        return $this->emailValidationTokens;
    }

    public function addEmailValidationToken(EmailValidationToken $token): self
    {
        if (!$this->emailValidationTokens->contains($token)) {
            $this->emailValidationTokens->add($token);
            $token->setUser($this);
        }

        return $this;
    }
}
