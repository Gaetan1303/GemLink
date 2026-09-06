<?php



namespace App\Repository;

use App\Entity\Pierre;
use App\Entity\Publication;
use App\Entity\PublicationPierre;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicationPierre>
 */
class PublicationPierreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationPierre::class);
    }

    /**
     * Upsert en un seul aller-retour SQL (ON CONFLICT) plutôt qu'un
     * find-or-create Doctrine (SELECT puis INSERT/UPDATE) : une requête au
     * lieu de deux, et pas de race condition si une même publication est
     * ré-analysée par deux workers en parallèle. S'appuie sur la contrainte
     * PRIMARY KEY (publication_id, pierre_id) déjà en base.
     *
     * IMPORTANT : passe par la connexion DBAL directement, donc écrit tout
     * de suite en base, hors de l'Unit of Work Doctrine. La Pierre référencée
     * doit donc déjà être flush (voir AnalyzeMediaMessageHandler).
     */
    public function upsertMatch(Publication $publication, Pierre $pierre, float $confidence): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO publication_pierre (publication_id, pierre_id, confidence)
             VALUES (:publicationId::uuid, :pierreId::uuid, :confidence)
             ON CONFLICT (publication_id, pierre_id)
             DO UPDATE SET confidence = EXCLUDED.confidence',
            [
                'publicationId' => $publication->getId()->toRfc4122(),
                'pierreId' => $pierre->getId()->toRfc4122(),
                'confidence' => $confidence,
            ]
        );
    }

    /**
     * Meilleure identification pour une publication (confidence la plus
     * élevée). Évite de dupliquer une colonne redondante "pierre_id" sur
     * Publication alors que le MLD modélise volontairement le many-to-many.
     */
    public function findBestMatch(Publication $publication): ?PublicationPierre
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.publication = :publication')
            ->setParameter('publication', $publication)
            ->orderBy('pp.confidence', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('pp')
            ->join('pp.publication', 'p')
            ->select('COUNT(p.id)')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();
    }

    public function countForUserAndPierre(User $user, Pierre $pierre): int
    {
        return (int) $this->createQueryBuilder('pp')
            ->join('pp.publication', 'p')
            ->select('COUNT(p.id)')
            ->andWhere('p.user = :user')
            ->andWhere('pp.pierre = :pierre')
            ->setParameter('user', $user)
            ->setParameter('pierre', $pierre)
            ->getQuery()->getSingleScalarResult();
    }
}
