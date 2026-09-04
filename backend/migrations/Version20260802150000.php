<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802150000 extends AbstractMigration
{
    public function getDescription(): string { return 'US 2.5 - configurable uncertain-identification confidence threshold'; }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO parametre_systeme (id, cle, valeur, updated_at) SELECT uuid_generate_v4(), 'identification.confidence_threshold', '0.4', NOW() WHERE NOT EXISTS (SELECT 1 FROM parametre_systeme WHERE cle = 'identification.confidence_threshold')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM parametre_systeme WHERE cle = 'identification.confidence_threshold'");
    }
}
