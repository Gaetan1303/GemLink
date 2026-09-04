<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 6.1 - masque automatiquement toute publication active ayant cinq signalements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION trigger_auto_moderate_publication() RETURNS TRIGGER AS $$
            DECLARE report_count INTEGER;
            BEGIN
                SELECT COUNT(*) INTO report_count
                FROM report
                WHERE publication_id = NEW.publication_id AND status != 'REJECTED';

                IF report_count >= 5 THEN
                    UPDATE publication
                    SET status = 'AUTO_HIDDEN', updated_at = NOW()
                    WHERE id = NEW.publication_id
                      AND deleted_at IS NULL
                      AND status != 'AUTO_HIDDEN';
                END IF;

                RETURN NEW;
            END; $$ LANGUAGE plpgsql
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION trigger_auto_moderate_publication() RETURNS TRIGGER AS $$
            DECLARE report_count INTEGER;
            BEGIN
                SELECT COUNT(*) INTO report_count
                FROM report
                WHERE publication_id = NEW.publication_id AND status != 'REJECTED';

                IF report_count >= 5 THEN
                    UPDATE publication
                    SET status = 'AUTO_HIDDEN', updated_at = NOW()
                    WHERE id = NEW.publication_id AND status = 'PUBLISHED';
                END IF;

                RETURN NEW;
            END; $$ LANGUAGE plpgsql
            SQL);
    }
}
