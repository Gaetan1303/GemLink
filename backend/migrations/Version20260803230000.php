<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 5.5 - recalcule les Trust Scores existants avec fiabilité et ancienneté';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            WITH trust_stats AS (
                SELECT
                    account.id AS user_id,
                    COUNT(validation.id) FILTER (
                        WHERE publication.status = 'COMMUNITY_VALIDATED'
                    ) AS total,
                    COUNT(validation.id) FILTER (
                        WHERE publication.status = 'COMMUNITY_VALIDATED'
                          AND (
                              (validation.action = 'CONFIRM' AND validation.pierre_id = winner.pierre_id)
                              OR (
                                  validation.action = 'CORRECT'
                                  AND LOWER(TRIM(validation.proposed_label)) = LOWER(TRIM(winner.pierre_name))
                              )
                          )
                    ) AS confirmed
                FROM utilisateur account
                LEFT JOIN validation ON validation.user_id = account.id
                LEFT JOIN publication ON publication.id = validation.publication_id
                LEFT JOIN LATERAL (
                    SELECT publication_pierre.pierre_id, pierre.name AS pierre_name
                    FROM publication_pierre
                    JOIN pierre ON pierre.id = publication_pierre.pierre_id
                    WHERE publication_pierre.publication_id = validation.publication_id
                    ORDER BY publication_pierre.confidence DESC
                    LIMIT 1
                ) winner ON TRUE
                GROUP BY account.id
            )
            UPDATE utilisateur account
            SET trust_score = CASE
                WHEN trust_stats.total = 0 THEN 0
                ELSE LEAST(100, GREATEST(0, ROUND(
                    100.0
                    * trust_stats.confirmed::numeric / trust_stats.total
                    * (0.5 + 0.5 * LEAST(1.0, trust_stats.total::numeric / 20.0))
                )::integer))
            END
            FROM trust_stats
            WHERE account.id = trust_stats.user_id
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Donnée calculée : aucun ancien score potentiellement obsolète à restaurer.
    }
}
