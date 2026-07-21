<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nouveau numéro de version (les précédents étant déjà marqués exécutés
 * et donc figés côté Doctrine, quel que soit leur contenu actuel).
 * Entièrement idempotent : chaque étape vérifie son prérequis avant
 * d'agir, donc sûr à rejouer peu importe ce qui a ou non été appliqué
 * par les migrations précédentes.
 */
final class Version20260725000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattrapage final US 4.1 : status, position, vitrine_media, publication_id, TIMESTAMPTZ(0).';
    }

    public function up(Schema $schema): void
    {
        // ── vitrine.status ──────────────────────────────────────
        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'vitrine' AND column_name = 'status'
                ) THEN
                    ALTER TABLE vitrine ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'DRAFT';
                END IF;
            END \$\$;
        ");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_vitrine_user_id ON vitrine (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_vitrine_status ON vitrine (status)');

        // ── vitrine_publication.position ───────────────────────
        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'vitrine_publication' AND column_name = 'position'
                ) THEN
                    ALTER TABLE vitrine_publication ADD COLUMN position INTEGER NOT NULL DEFAULT 0;
                END IF;
            END \$\$;
        ");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_vitrine_publication_position ON vitrine_publication (vitrine_id, position)');

        // ── vitrine_media (table entière) ──────────────────────
        $this->addSql('
            CREATE TABLE IF NOT EXISTS vitrine_media (
                id          UUID           NOT NULL DEFAULT uuid_generate_v4(),
                vitrine_id  UUID           NOT NULL,
                media_url   TEXT           NOT NULL DEFAULT \'\',
                media_type  media_type     NOT NULL,
                position    INTEGER        NOT NULL DEFAULT 0,
                created_at  TIMESTAMPTZ(0) NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id)
            )
        ');
        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'fk_vitrine_media_vitrine'
                ) THEN
                    ALTER TABLE vitrine_media
                        ADD CONSTRAINT fk_vitrine_media_vitrine
                        FOREIGN KEY (vitrine_id) REFERENCES vitrine(id) ON DELETE CASCADE;
                END IF;
            END \$\$;
        ");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_vitrine_media_position ON vitrine_media (vitrine_id, position)');

        // ── vitrine.publication_id (post généré à la publication) ──
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

        // ── Correction de précision TIMESTAMPTZ (bug microsecondes) ──
        $this->addSql('ALTER TABLE vitrine ALTER COLUMN created_at TYPE TIMESTAMPTZ(0)');
        $this->addSql('ALTER TABLE vitrine ALTER COLUMN updated_at TYPE TIMESTAMPTZ(0)');
        $this->addSql('ALTER TABLE vitrine_publication ALTER COLUMN added_at TYPE TIMESTAMPTZ(0)');
        $this->addSql('ALTER TABLE vitrine_media ALTER COLUMN created_at TYPE TIMESTAMPTZ(0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vitrine DROP CONSTRAINT IF EXISTS fk_vitrine_generated_publication');
        $this->addSql('ALTER TABLE vitrine DROP CONSTRAINT IF EXISTS uq_vitrine_publication');
        $this->addSql('ALTER TABLE vitrine DROP COLUMN IF EXISTS publication_id');
        $this->addSql('DROP TABLE IF EXISTS vitrine_media');
        $this->addSql('ALTER TABLE vitrine_publication DROP COLUMN IF EXISTS position');
        $this->addSql('ALTER TABLE vitrine DROP COLUMN IF EXISTS status');
    }
}