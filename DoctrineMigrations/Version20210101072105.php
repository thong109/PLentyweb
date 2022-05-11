<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210101072105 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dtb_product ADD ec_link TEXT AFTER free_area');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dtb_product');
    }
}

