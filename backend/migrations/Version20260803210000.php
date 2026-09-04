<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 3.3 - ajoute les dates de suivi des cycles de fine-tuning ViT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_fine_tuning ADD COLUMN IF NOT EXISTS started_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE job_fine_tuning ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_fine_tuning DROP COLUMN IF EXISTS completed_at');
        $this->addSql('ALTER TABLE job_fine_tuning DROP COLUMN IF EXISTS started_at');
    }
}
