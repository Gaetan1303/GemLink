<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create email_validation_token and password_reset_token tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_validation_token (id UUID NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, used BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B91D4C9FA76ED395 ON email_validation_token (user_id)');
        $this->addSql('ALTER TABLE email_validation_token ADD CONSTRAINT FK_B91D4C9FA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE password_reset_token (id UUID NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, used BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6B7BA4B6A76ED395 ON password_reset_token (user_id)');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_6B7BA4B6A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_validation_token DROP CONSTRAINT FK_B91D4C9FA76ED395');
        $this->addSql('ALTER TABLE password_reset_token DROP CONSTRAINT FK_6B7BA4B6A76ED395');
        $this->addSql('DROP TABLE email_validation_token');
        $this->addSql('DROP TABLE password_reset_token');
    }
}
