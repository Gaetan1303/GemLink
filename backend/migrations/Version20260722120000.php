<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * US 4.2 - CA-3 : ajoute vitrine.qr_code_url, manquant côté base (jamais
 * appliqué — perdu dans le rattrapage de schéma de US 4.1). Idempotent,
 * même pattern que Version20260723090000 / Version20260725000000 :
 * sûr à rejouer quel que soit l'état exact de la base.
 */
final class Version20260726000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 4.2 - Ajoute vitrine.qr_code_url (idempotent).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'vitrine' AND column_name = 'qr_code_url'
                ) THEN
                    ALTER TABLE vitrine ADD COLUMN qr_code_url VARCHAR(500) DEFAULT NULL;
                END IF;
            END \$\$;
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vitrine DROP COLUMN IF EXISTS qr_code_url');
    }
}