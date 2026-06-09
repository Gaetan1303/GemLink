<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alter embedding.vector_data to vector(512)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE embedding ALTER COLUMN vector_data TYPE vector(512) USING vector_data::vector;");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE embedding ALTER COLUMN vector_data TYPE vector USING vector_data::vector;");
    }
}
