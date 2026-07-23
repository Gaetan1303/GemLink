<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Vitrine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Vitrine>
 */
class VitrineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vitrine::class);
    }

    public function save(Vitrine $vitrine, bool $flush = true): void
    {
        $this->getEntityManager()->persist($vitrine);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBySlug(string $slug): ?Vitrine
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * US 4.2 - CA-1 : variante de findBySlug() restreinte aux Vitrines
     * publiées, utilisée par la page publique (VitrinePublicController).
     * Une Vitrine en DRAFT ne doit jamais être accessible via son slug par
     * un visiteur non authentifié.
     */
    public function findOnePublishedBySlug(string $slug): ?Vitrine
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.slug = :slug')
            ->andWhere('v.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', Vitrine::STATUS_PUBLISHED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * US 4.2 - CA-2 : incrément atomique en base du compteur de vues,
     * appelé par le worker de flush périodique (toutes les 60s) avec le
     * total accumulé en Redis depuis le dernier flush. Volontairement une
     * requête UPDATE directe (pas de find() + flush()) : pas besoin
     * d'hydrater l'entité pour un simple incrément, et ça évite tout
     * risque d'écraser une valeur avec une entité potentiellement obsolète
     * si elle était déjà en mémoire ailleurs dans la requête.
     */
    public function incrementViewCount(string $vitrineId, int $increment): void
    {
        $this->getEntityManager()->createQuery(
            'UPDATE App\Entity\Vitrine v SET v.viewCount = v.viewCount + :increment WHERE v.id = :id'
        )
            ->setParameter('increment', $increment)
            ->setParameter('id', Uuid::fromString($vitrineId))
            ->execute();
    }

    /**
     * @return Vitrine[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.user = :user')
            ->setParameter('user', $user)
            ->orderBy('v.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * CA-1 : slug lowercase, tirets à la place des espaces, sans caractères
     * spéciaux ; suffixe numérique -2, -3... en cas de collision.
     */
    public function generateUniqueSlug(string $title, ?Uuid $excludeId = null): string
    {
        $base = $this->slugify($title);

        if ($base === '') {
            $base = 'vitrine';
        }

        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = sprintf('%s-%d', $base, $suffix);
            ++$suffix;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?Uuid $excludeId): bool
    {
        $qb = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('v.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    private function slugify(string $value): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
        $lower = mb_strtolower($transliterated !== false ? $transliterated : $value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        // VARCHAR(150) en base — on garde de la marge pour un éventuel suffixe.
        return trim(mb_substr($slug, 0, 140), '-');
    }
}
