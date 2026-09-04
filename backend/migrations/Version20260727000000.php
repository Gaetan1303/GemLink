<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * US 2.4 — Commentaires MVP.
 * Entièrement idempotent (mêmes garanties que Version20260725000000) :
 * chaque étape vérifie son prérequis avant d'agir.
 */
final class Version20260727000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 2.4 : contrainte de longueur sur commentaire.content (CA-1) + index de pagination cursor-based (CA-3).';
    }

    public function up(Schema $schema): void
    {
        // CA-1 : un commentaire est limité à 1000 caractères. Contrainte
        // posée en base en plus de la validation applicative (défense en
        // profondeur, cohérent avec le CHECK déjà présent sur facture.amount).
        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'chk_commentaire_content_length'
                ) THEN
                    ALTER TABLE commentaire
                        ADD CONSTRAINT chk_commentaire_content_length CHECK (char_length(content) <= 1000);
                END IF;
            END \$\$;
        ");

        // CA-3 : pagination cursor-based triée par (publication_id, created_at, id).
        // L'index idx_commentaire_pub_id existant ne couvre que la colonne de
        // filtrage ; celui-ci couvre aussi le tri pour éviter un sort en mémoire.
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_commentaire_pub_created_id ON commentaire (publication_id, created_at, id)');

        // CA-2 : les suppressions/consultations de commentaires actifs
        // filtrent systématiquement sur deleted_at IS NULL.
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_commentaire_deleted_at ON commentaire (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_commentaire_deleted_at');
        $this->addSql('DROP INDEX IF EXISTS idx_commentaire_pub_created_id');
        $this->addSql('ALTER TABLE commentaire DROP CONSTRAINT IF EXISTS chk_commentaire_content_length');
    }
}
