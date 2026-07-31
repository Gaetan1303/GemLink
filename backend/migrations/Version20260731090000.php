<?php
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260731090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Identification publique temporaire, expiree apres une heure'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE public_identification (id UUID NOT NULL, requester_key VARCHAR(64) NOT NULL, media_url TEXT NOT NULL, mime_type VARCHAR(100) NOT NULL, status VARCHAR(40) NOT NULL, result JSON DEFAULT NULL, created_at TIMESTAMPTZ NOT NULL, expires_at TIMESTAMPTZ NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_public_identification_expires_at ON public_identification (expires_at)');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE public_identification'); }
}
