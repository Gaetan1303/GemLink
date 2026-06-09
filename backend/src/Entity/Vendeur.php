<?php

        declare(strict_types=1);

        namespace App\Entity;

        use DateTimeImmutable;
        use Doctrine\ORM\Mapping as ORM;
        use Symfony\Component\Uid\Uuid;

        #[ORM\Entity]
        #[ORM\Table(name: 'vendeur')]
        class Vendeur
        {
            #[ORM\Id]
            #[ORM\Column(type: 'uuid', unique: true)]
            private Uuid $id;

            #[ORM\ManyToOne(targetEntity: User::class)]
            #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
            private User $user;

            #[ORM\Column(length: 150)]
            private string $companyName = '';

            #[ORM\Column(length: 14)]
            private string $siret = '';

            #[ORM\Column(type: 'text', nullable: true)]
            private ?string $address = null;

            #[ORM\Column(length: 50, nullable: true)]
            private ?string $subscriptionPlan = null;

            #[ORM\Column(name: 'subscription_expires_at', type: 'datetimetz_immutable', nullable: true)]
            private ?DateTimeImmutable $subscriptionExpiresAt = null;

            #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
            private DateTimeImmutable $createdAt;

            public function __construct(User $user, string $companyName, string $siret)
            {
                $this->id = Uuid::v7();
                $this->user = $user;
                $this->companyName = $companyName;
                $this->siret = $siret;
                $this->createdAt = new DateTimeImmutable();
            }

            public function getId(): Uuid
            {
                return $this->id;
            }
        }
