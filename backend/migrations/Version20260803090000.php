<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'US 5.5 - remplace le trigger erroné par le calcul applicatif du Trust Score des validateurs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS update_trust_score_after_validation ON validation');
        $this->addSql('DROP FUNCTION IF EXISTS trigger_recalculate_trust_score()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION trigger_recalculate_trust_score() RETURNS TRIGGER AS $$
            BEGIN
                RETURN COALESCE(NEW, OLD);
            END; $$ LANGUAGE plpgsql
            SQL);
        $this->addSql('CREATE TRIGGER update_trust_score_after_validation AFTER INSERT OR UPDATE OR DELETE ON validation FOR EACH ROW EXECUTE FUNCTION trigger_recalculate_trust_score()');
    }
}
