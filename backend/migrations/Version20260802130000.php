<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 5.2 - database-backed configurable levels and existing-user level backfill';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE niveau (
                id UUID NOT NULL,
                number SMALLINT NOT NULL CHECK (number >= 1),
                name VARCHAR(50) NOT NULL,
                min_points INTEGER NOT NULL CHECK (min_points >= 0),
                badge_id UUID DEFAULT NULL,
                PRIMARY KEY (id),
                CONSTRAINT uq_niveau_number UNIQUE (number),
                CONSTRAINT uq_niveau_min_points UNIQUE (min_points),
                CONSTRAINT fk_niveau_badge FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE SET NULL
            )
            SQL);

        foreach ([[1, 'Novice', 0], [2, 'Initié', 100], [3, 'Connaisseur', 500], [4, 'Expert', 2000], [5, 'Maître', 10000]] as [$number, $name, $minPoints]) {
            $this->addSql(sprintf("INSERT INTO niveau (id, number, name, min_points) VALUES (uuid_generate_v4(), %d, '%s', %d)", $number, $name, $minPoints));
        }

        $this->addSql('UPDATE utilisateur u SET level = COALESCE((SELECT n.number FROM niveau n WHERE n.min_points <= u.points ORDER BY n.min_points DESC LIMIT 1), 1)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE niveau');
    }
}
