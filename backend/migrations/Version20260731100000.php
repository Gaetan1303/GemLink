<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731100000 extends AbstractMigration
{
    public function getDescription(): string { return 'US 6.3: moderation utilisateur, suivi des sessions et progression du fine-tuning'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD COLUMN IF NOT EXISTS banned_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD COLUMN IF NOT EXISTS banned_until TIMESTAMPTZ DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMPTZ DEFAULT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_utilisateur_last_login_at ON utilisateur (last_login_at)');
        $this->addSql('ALTER TABLE job_fine_tuning ADD COLUMN IF NOT EXISTS progress SMALLINT NOT NULL DEFAULT 0 CHECK (progress BETWEEN 0 AND 100)');
        $this->addSql('ALTER TABLE job_fine_tuning ADD COLUMN IF NOT EXISTS error_message TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_utilisateur_last_login_at');
        $this->addSql('ALTER TABLE utilisateur DROP COLUMN IF EXISTS last_login_at, DROP COLUMN IF EXISTS banned_until, DROP COLUMN IF EXISTS banned_reason');
        $this->addSql('ALTER TABLE job_fine_tuning DROP COLUMN IF EXISTS error_message, DROP COLUMN IF EXISTS progress');
    }
}
