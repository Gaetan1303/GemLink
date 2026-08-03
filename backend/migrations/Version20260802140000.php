<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802140000 extends AbstractMigration
{
    public function getDescription(): string { return 'US 5.3 - configurable automatic badges and mineral badge prototypes'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TYPE badge_condition_type ADD VALUE IF NOT EXISTS 'STONE_IDENTIFICATION_COUNT'");
        $this->addSql("ALTER TYPE badge_condition_type ADD VALUE IF NOT EXISTS 'MINERAL_IDENTIFICATION_COUNT'");
        $this->addSql('ALTER TABLE badge ADD pierre_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD content TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE badge ADD CONSTRAINT fk_badge_pierre FOREIGN KEY (pierre_id) REFERENCES pierre (id) ON DELETE CASCADE');
        $this->addSql("CREATE UNIQUE INDEX uq_badge_mineral_identification ON badge (pierre_id) WHERE condition_type = 'MINERAL_IDENTIFICATION_COUNT'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uq_badge_mineral_identification');
        $this->addSql('ALTER TABLE badge DROP CONSTRAINT fk_badge_pierre');
        $this->addSql('ALTER TABLE badge DROP COLUMN pierre_id');
        $this->addSql('ALTER TABLE notification DROP COLUMN content');
    }
}
