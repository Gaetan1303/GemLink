<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrige le même bug de précision que sur publication : TIMESTAMPTZ sans
 * précision stocke les microsecondes de NOW(), que
 * Doctrine\DBAL\Types\DateTimeTzImmutableType ne sait pas relire
 * (format attendu "Y-m-d H:i:sO", pas de microsecondes).
 */
final class Version20260723090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Force TIMESTAMPTZ(0) sur les colonnes de date de vitrine/vitrine_publication/vitrine_media.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vitrine ALTER COLUMN created_at TYPE TIMESTAMPTZ(0)');
        $this->addSql('ALTER TABLE vitrine ALTER COLUMN updated_at TYPE TIMESTAMPTZ(0)');
        $this->addSql('ALTER TABLE vitrine_publication ALTER COLUMN added_at TYPE TIMESTAMPTZ(0)');
        $this->addSql('ALTER TABLE vitrine_media ALTER COLUMN created_at TYPE TIMESTAMPTZ(0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vitrine ALTER COLUMN created_at TYPE TIMESTAMPTZ');
        $this->addSql('ALTER TABLE vitrine ALTER COLUMN updated_at TYPE TIMESTAMPTZ');
        $this->addSql('ALTER TABLE vitrine_publication ALTER COLUMN added_at TYPE TIMESTAMPTZ');
        $this->addSql('ALTER TABLE vitrine_media ALTER COLUMN created_at TYPE TIMESTAMPTZ');
    }
}