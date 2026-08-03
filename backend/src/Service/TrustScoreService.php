<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class TrustScoreService
{
    private const FULL_SENIORITY_AT = 20;

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function recalculate(User $validator): int
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (
                        WHERE
                            (v.action = 'CONFIRM' AND v.pierre_id = winner.pierre_id)
                            OR
                            (
                                v.action = 'CORRECT'
                                AND LOWER(TRIM(v.proposed_label)) = LOWER(TRIM(winner.pierre_name))
                            )
                    ) AS confirmed
                FROM validation v
                JOIN publication p ON p.id = v.publication_id
                JOIN LATERAL (
                    SELECT pp.pierre_id, stone.name AS pierre_name
                    FROM publication_pierre pp
                    JOIN pierre stone ON stone.id = pp.pierre_id
                    WHERE pp.publication_id = v.publication_id
                    ORDER BY pp.confidence DESC
                    LIMIT 1
                ) winner ON TRUE
                WHERE v.user_id = :userId
                  AND p.status = 'COMMUNITY_VALIDATED'
                SQL,
            ['userId' => $validator->getId()->toRfc4122()],
        );

        $total = (int) ($row['total'] ?? 0);
        $confirmed = (int) ($row['confirmed'] ?? 0);
        if ($total === 0) {
            $validator->setTrustScore(0);
            $this->em->flush();
            return 0;
        }

        $score = $this->calculate($confirmed, $total);
        $validator->setTrustScore($score);
        $this->em->flush();

        return $validator->getTrustScore();
    }

    private function calculate(int $confirmed, int $total): int
    {
        $reliability = $confirmed / $total;
        $seniorityFactor = 0.5 + 0.5 * min(1, $total / self::FULL_SENIORITY_AT);

        return max(0, min(100, (int) round(100 * $reliability * $seniorityFactor)));
    }
}
