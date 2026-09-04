<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 6.2 - motif et index de consultation du journal de modération';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_log ADD COLUMN reason TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_audit_log_target_history ON audit_log (target_type, target_id, created_at)');
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION trigger_auto_moderate_publication() RETURNS TRIGGER AS $$
            DECLARE report_count INTEGER;
            BEGIN
                IF TG_OP = 'UPDATE' AND OLD.status != 'REJECTED' AND NEW.status = 'REJECTED' THEN
                    UPDATE publication
                    SET status = 'PUBLISHED', updated_at = NOW()
                    WHERE id = NEW.publication_id AND status = 'AUTO_HIDDEN';
                ELSE
                    SELECT COUNT(*) INTO report_count
                    FROM report
                    WHERE publication_id = NEW.publication_id AND status != 'REJECTED';

                    IF report_count >= 5 THEN
                        UPDATE publication
                        SET status = 'AUTO_HIDDEN', updated_at = NOW()
                        WHERE id = NEW.publication_id AND status = 'PUBLISHED';
                    END IF;
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
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_target_history');
        $this->addSql('ALTER TABLE audit_log DROP COLUMN reason');
    }
}
