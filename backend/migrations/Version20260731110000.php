<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731110000 extends AbstractMigration
{
    public function getDescription(): string { return 'Garantit un Trust Score de 100 pour les administrateurs existants.'; }

    public function up(Schema $schema): void { $this->addSql("UPDATE utilisateur SET trust_score = 100 WHERE role = 'ADMIN'"); }
    public function down(Schema $schema): void { }
}
