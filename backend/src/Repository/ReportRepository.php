<?php

namespace App\Repository;

use App\Entity\Report;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Report>
 */
class ReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Report::class);
    }

    /** @return Report[] */
    public function findForModeration(string $status = 'PENDING', int $limit = 100): array
    {
        $countForPublication = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(sibling.id)')
            ->from(Report::class, 'sibling')
            ->where('sibling.publication = publication')
            ->andWhere('sibling.status = :status')
            ->getDQL();

        return $this->createQueryBuilder('report')
            ->addSelect('user', 'publication', 'author')
            ->addSelect(sprintf('(%s) AS HIDDEN reportCount', $countForPublication))
            ->join('report.user', 'user')
            ->join('report.publication', 'publication')
            ->join('publication.user', 'author')
            ->andWhere('report.status = :status')
            ->setParameter('status', $status)
            ->orderBy('reportCount', 'DESC')
            ->addOrderBy('report.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    //    /**
    //     * @return Report[] Returns an array of Report objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Report
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
