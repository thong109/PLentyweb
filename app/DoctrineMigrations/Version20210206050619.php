<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210206050619 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dtb_cart ADD store_id INT NULL AFTER pre_order_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dtb_cart');
    }
}
