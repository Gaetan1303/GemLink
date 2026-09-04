<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 2.2 - tags d interesse utilisateur pour le feed personnalise';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_interest_tag (user_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(user_id, tag_id))');
        $this->addSql('CREATE INDEX idx_user_interest_tag_tag ON user_interest_tag (tag_id)');
        $this->addSql('CREATE INDEX idx_publication_pierre_feed_filter ON publication_pierre (pierre_id, confidence)');
        $this->addSql('ALTER TABLE user_interest_tag ADD CONSTRAINT fk_user_interest_tag_user FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_interest_tag ADD CONSTRAINT fk_user_interest_tag_tag FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_interest_tag');
    }
}
