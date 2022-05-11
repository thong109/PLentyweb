<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210317153545 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dtb_cart_item ADD schedule_id INTEGER (11) NULL AFTER quantity');
        $this->addSql('ALTER TABLE dtb_cart_item ADD rental_min_day INTEGER (11) NULL AFTER schedule_id');
        $this->addSql('ALTER TABLE dtb_cart_item ADD rental_start_date DATETIME NULL AFTER rental_min_day');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
