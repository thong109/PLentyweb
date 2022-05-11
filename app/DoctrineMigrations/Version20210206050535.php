<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210206050535 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
//        $this->addSql('ALTER TABLE dtb_product ADD store_id INT NULL AFTER ec_link');
    }

    public function down(Schema $schema): void
    {
//        $this->addSql('DROP TABLE dtb_product');
    }
}
