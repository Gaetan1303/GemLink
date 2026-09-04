<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronise la colonne de paiement attendue par Facture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture ADD COLUMN IF NOT EXISTS paid_at TIMESTAMPTZ(0) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE facture DROP COLUMN IF EXISTS paid_at');
    }
}
