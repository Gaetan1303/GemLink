<?php

namespace App\Repository;

use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Validation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Validation>
 */
class ValidationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Validation::class);
    }

    /**
     * Une seule ligne par couple (publication, user) — contrainte
     * uq_validation_pub_user. C'est la clé de l'upsert dans
     * ValidationService::submitValidation() (CA-1, CA-2).
     */
    public function findOneByPublicationAndUser(Publication $publication, User $user): ?Validation
    {
        return $this->findOneBy(['publication' => $publication, 'user' => $user]);
    }

    /**
     * Toutes les validations d'une publication — chaque validateur n'ayant
     * qu'une seule ligne, c'est directement l'ensemble des votes actuels
     * (CA-3, CA-4), sans logique de "dernière validation" à appliquer.
     *
     * @return Validation[]
     */
    public function findByPublication(Publication $publication): array
    {
        return $this->findBy(['publication' => $publication]);
    }

    /**
     * Validations dont le Trust Score du validateur dépasse le seuil Admin
     * (CA-5), calculées à la volée plutôt que via un flag dénormalisé.
     *
     * @return Validation[]
     */
    public function findDatasetCandidates(int $trustScoreThreshold, int $limit = 500): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('validator', 'publication', 'pierre')
            ->join('v.user', 'validator')
            ->join('v.publication', 'publication')
            ->join('v.pierre', 'pierre')
            ->andWhere('validator.trustScore >= :threshold')
            ->andWhere('publication.status = :validatedStatus')
            ->setParameter('threshold', $trustScoreThreshold)
            ->setParameter('validatedStatus', Publication::STATUS_COMMUNITY_VALIDATED)
            ->orderBy('v.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
