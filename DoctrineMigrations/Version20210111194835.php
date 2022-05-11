<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210111194835 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('CREATE TABLE dtb_store_delivery (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          store_id            INT UNSIGNED       NOT NULL,
          delivery_id         INT UNSIGNED       NOT NULL,
          discriminator_type VARCHAR(255)        NULL,
          updated_at        DATETIME             NULL,
          created_at        DATETIME             NULL)');

        $this->addSql('ALTER TABLE dtb_store_delivery ADD CONSTRAINT fk_store_id_delivery FOREIGN KEY (store_id) REFERENCES dtb_store(id)');
        $this->addSql('ALTER TABLE dtb_store_delivery ADD CONSTRAINT fk_pivot_delivery_id FOREIGN KEY (delivery_id) REFERENCES dtb_delivery(id)');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE dtb_store_delivery');
    }
}
