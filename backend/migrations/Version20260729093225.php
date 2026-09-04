<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * US 2.7 — Validation communautaire de l'identification IA.
 *
 * La table `validation` existe déjà depuis le schéma initial
 * (Version20260609113454) avec son propre trigger de recalcul du Trust
 * Score — cette migration ne la touche pas. Seuls ajouts réels :
 *  - la table parametre_systeme (seuils Admin configurables, CA-4/CA-5)
 *  - la valeur COMMUNITY_VALIDATED sur l'enum Postgres natif post_status
 *    (cf. doctrine.yaml : post_status est mappé en "string" côté Doctrine
 *    mais reste un vrai ENUM au niveau moteur, qui rejette toute valeur
 *    non déclarée)
 */
final class Version20260729093225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 2.7 - parametre_systeme + valeur COMMUNITY_VALIDATED sur post_status';
    }

    /**
     * ALTER TYPE ... ADD VALUE ne peut pas s'exécuter dans la même
     * transaction qu'une requête utilisant la nouvelle valeur.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t
                    JOIN pg_enum e ON e.enumtypid = t.oid
                    WHERE t.typname = 'post_status' AND e.enumlabel = 'COMMUNITY_VALIDATED'
                ) THEN
                    ALTER TYPE post_status ADD VALUE 'COMMUNITY_VALIDATED';
                END IF;
            END $$;
            SQL);

        $this->addSql('
            CREATE TABLE IF NOT EXISTS parametre_systeme (
                id         UUID           NOT NULL DEFAULT uuid_generate_v4(),
                cle        VARCHAR(100)   NOT NULL,
                valeur     VARCHAR(255)   NOT NULL,
                updated_at TIMESTAMPTZ(0) NOT NULL DEFAULT NOW(),
                PRIMARY KEY (id)
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_parametre_systeme_cle ON parametre_systeme (cle)');

        $this->addSql("
            INSERT INTO parametre_systeme (id, cle, valeur, updated_at)
            SELECT uuid_generate_v4(), 'validation.consensus_threshold', '0.66', NOW()
            WHERE NOT EXISTS (SELECT 1 FROM parametre_systeme WHERE cle = 'validation.consensus_threshold')
        ");
        $this->addSql("
            INSERT INTO parametre_systeme (id, cle, valeur, updated_at)
            SELECT uuid_generate_v4(), 'validation.dataset_candidate_trust_threshold', '70', NOW()
            WHERE NOT EXISTS (SELECT 1 FROM parametre_systeme WHERE cle = 'validation.dataset_candidate_trust_threshold')
        ");
    }

    public function down(Schema $schema): void
    {
        // COMMUNITY_VALIDATED n'est pas retiré de l'enum : PostgreSQL ne
        // supporte pas ALTER TYPE ... DROP VALUE. Rollback partiel assumé.
        $this->addSql('DROP TABLE IF EXISTS parametre_systeme');
    }
}
