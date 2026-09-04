<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825130000 extends AbstractMigration
{
    public function getDescription(): string { return 'Renforce les invariants, rôles et index des factions et conversations'; }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE groupe_member SET role = 'ADMIN' WHERE role = 'OFFICER'");
        $this->addSql('ALTER TABLE groupe_member DROP CONSTRAINT IF EXISTS uq_groupe_member_group_user');
        $this->addSql("CREATE UNIQUE INDEX uq_groupe_member_active ON groupe_member (groupe_id, user_id) WHERE status = 'ACTIVE'");
        $this->addSql('CREATE INDEX idx_groupe_member_user_status ON groupe_member (user_id, status)');
        $this->addSql("ALTER TABLE groupe_member ADD CONSTRAINT chk_groupe_member_role CHECK (role IN ('OWNER','ADMIN','MEMBER'))");
        $this->addSql("ALTER TABLE groupe_member ADD CONSTRAINT chk_groupe_member_status CHECK (status IN ('ACTIVE','LEFT','REMOVED'))");
        $this->addSql('CREATE INDEX idx_groupe_join_request_group_status ON groupe_join_request (groupe_id, status)');
        $this->addSql('CREATE INDEX idx_groupe_join_request_requester_status ON groupe_join_request (requester_id, status)');
        $this->addSql("ALTER TABLE groupe_join_request ADD CONSTRAINT chk_groupe_join_request_status CHECK (status IN ('PENDING','ACCEPTED','REJECTED','CANCELLED'))");
        $this->addSql("ALTER TABLE groupe ADD CONSTRAINT chk_groupe_visibility CHECK (visibility IN ('PUBLIC','PRIVATE'))");
        $this->addSql("ALTER TABLE groupe ADD CONSTRAINT chk_groupe_status CHECK (status IN ('ACTIVE','ARCHIVED'))");
        $this->addSql('ALTER TABLE groupe ADD CONSTRAINT chk_groupe_name_length CHECK (char_length(name) BETWEEN 3 AND 100)');
        $this->addSql('ALTER TABLE groupe ADD CONSTRAINT chk_groupe_description_length CHECK (description IS NULL OR char_length(description) <= 2000)');
        $this->addSql("ALTER TABLE conversation ADD CONSTRAINT chk_conversation_shape CHECK ((type = 'DIRECT' AND groupe_id IS NULL AND direct_key IS NOT NULL) OR (type = 'FACTION' AND groupe_id IS NOT NULL AND direct_key IS NULL))");
        $this->addSql('CREATE INDEX idx_conversation_last_message_at ON conversation (last_message_at DESC)');
        $this->addSql('CREATE INDEX idx_conversation_participant_user ON conversation_participant (user_id)');
        $this->addSql('CREATE INDEX idx_chat_message_author ON chat_message (author_id)');
        $this->addSql('ALTER TABLE chat_message DROP CONSTRAINT fk_chat_message_author');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT fk_chat_message_author FOREIGN KEY (author_id) REFERENCES utilisateur (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT fk_cp_last_read_message FOREIGN KEY (last_read_message_id) REFERENCES chat_message (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_participant DROP CONSTRAINT fk_cp_last_read_message');
        $this->addSql('ALTER TABLE chat_message DROP CONSTRAINT fk_chat_message_author');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT fk_chat_message_author FOREIGN KEY (author_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_chat_message_author');
        $this->addSql('DROP INDEX idx_conversation_participant_user');
        $this->addSql('DROP INDEX idx_conversation_last_message_at');
        $this->addSql('ALTER TABLE conversation DROP CONSTRAINT chk_conversation_shape');
        $this->addSql('ALTER TABLE groupe DROP CONSTRAINT chk_groupe_description_length');
        $this->addSql('ALTER TABLE groupe DROP CONSTRAINT chk_groupe_name_length');
        $this->addSql('ALTER TABLE groupe DROP CONSTRAINT chk_groupe_status');
        $this->addSql('ALTER TABLE groupe DROP CONSTRAINT chk_groupe_visibility');
        $this->addSql('ALTER TABLE groupe_join_request DROP CONSTRAINT chk_groupe_join_request_status');
        $this->addSql('DROP INDEX idx_groupe_join_request_requester_status');
        $this->addSql('DROP INDEX idx_groupe_join_request_group_status');
        $this->addSql('ALTER TABLE groupe_member DROP CONSTRAINT chk_groupe_member_status');
        $this->addSql('ALTER TABLE groupe_member DROP CONSTRAINT chk_groupe_member_role');
        $this->addSql('DROP INDEX idx_groupe_member_user_status');
        $this->addSql('DROP INDEX uq_groupe_member_active');
        $this->addSql('ALTER TABLE groupe_member ADD CONSTRAINT uq_groupe_member_group_user UNIQUE (groupe_id, user_id)');
    }
}
