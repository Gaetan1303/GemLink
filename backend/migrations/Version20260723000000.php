<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Idempotent : ajoute vitrine.publication_id seulement si elle n'existe
 * pas déjà (au cas où une exécution précédente aurait partiellement
 * réussi avant d'échouer sur autre chose).
 */
final class Version20260724000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute vitrine.publication_id : Post généré automatiquement à la publication (idempotent).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'vitrine' AND column_name = 'publication_id'
                ) THEN
                    ALTER TABLE vitrine ADD COLUMN publication_id UUID DEFAULT NULL;
                END IF;
            END \$\$;
        ");

        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'uq_vitrine_publication'
                ) THEN
                    ALTER TABLE vitrine ADD CONSTRAINT uq_vitrine_publication UNIQUE (publication_id);
                END IF;
            END \$\$;
        ");

        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'fk_vitrine_generated_publication'
                ) THEN
                    ALTER TABLE vitrine
                        ADD CONSTRAINT fk_vitrine_generated_publication
                        FOREIGN KEY (publication_id) REFERENCES publication (id) ON DELETE SET NULL;
                END IF;
            END \$\$;
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vitrine DROP CONSTRAINT IF EXISTS fk_vitrine_generated_publication');
        $this->addSql('ALTER TABLE vitrine DROP CONSTRAINT IF EXISTS uq_vitrine_publication');
        $this->addSql('ALTER TABLE vitrine DROP COLUMN IF EXISTS publication_id');
    }
}