<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 2.3 - likes uniques par post/utilisateur et notifications de like dedupliquees';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE publication_like (publication_id UUID NOT NULL, user_id UUID NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(), PRIMARY KEY (publication_id, user_id))');
        $this->addSql('CREATE INDEX idx_publication_like_user ON publication_like (user_id)');
        $this->addSql('ALTER TABLE publication_like ADD CONSTRAINT fk_publication_like_publication FOREIGN KEY (publication_id) REFERENCES publication (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE publication_like ADD CONSTRAINT fk_publication_like_user FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE notification ADD actor_user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT fk_notification_actor_user FOREIGN KEY (actor_user_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        // Une notification est conservée après un unlike : reliker ne doit pas en recréer une.
        $this->addSql("CREATE UNIQUE INDEX uq_notification_like_actor_target ON notification (user_id, actor_user_id, target_id, target_type, type) WHERE type = 'NEW_LIKE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uq_notification_like_actor_target');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT IF EXISTS fk_notification_actor_user');
        $this->addSql('ALTER TABLE notification DROP COLUMN IF EXISTS actor_user_id');
        $this->addSql('DROP TABLE publication_like');
    }
}
