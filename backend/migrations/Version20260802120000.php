<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 5.1 - points ledger and configurable points scale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE point_transaction (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                action VARCHAR(50) NOT NULL,
                amount SMALLINT NOT NULL,
                source_id UUID NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id),
                CONSTRAINT fk_point_transaction_user FOREIGN KEY (user_id) REFERENCES utilisateur (id) ON DELETE CASCADE,
                CONSTRAINT uq_point_transaction_source UNIQUE (user_id, action, source_id)
            )
            SQL);
        $this->addSql('CREATE INDEX idx_point_transaction_user_date ON point_transaction (user_id, created_at DESC)');

        foreach ([
            'points.post_created' => '10',
            'points.like_received' => '2',
            'points.validation_submitted' => '5',
            'points.validation_consensus_confirmed' => '15',
        ] as $key => $value) {
            $this->addSql(sprintf(
                "INSERT INTO parametre_systeme (id, cle, valeur, updated_at) SELECT uuid_generate_v4(), '%s', '%s', NOW() WHERE NOT EXISTS (SELECT 1 FROM parametre_systeme WHERE cle = '%s')",
                $key,
                $value,
                $key,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM parametre_systeme WHERE cle IN ('points.post_created', 'points.like_received', 'points.validation_submitted', 'points.validation_consensus_confirmed')");
        $this->addSql('DROP TABLE point_transaction');
    }
}
