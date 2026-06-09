<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608085600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, username VARCHAR(30) NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, avatar_url TEXT DEFAULT NULL, bio VARCHAR(500) DEFAULT NULL, trust_score SMALLINT DEFAULT 0 NOT NULL, role VARCHAR(20) DEFAULT \'user\' NOT NULL, points INT DEFAULT 0 NOT NULL, level SMALLINT DEFAULT 1 NOT NULL, status VARCHAR(25) DEFAULT \'PENDING_VALIDATION\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_user_role ON "user" (role)');
        $this->addSql('CREATE INDEX idx_user_status ON "user" (status)');
        $this->addSql('CREATE UNIQUE INDEX idx_user_email ON "user" (email)');
        $this->addSql('CREATE UNIQUE INDEX idx_user_username ON "user" (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE "user"');
    }
}
