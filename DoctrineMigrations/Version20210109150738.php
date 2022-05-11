<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210109150738 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('CREATE TABLE dtb_store_member (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          store_id            INT UNSIGNED        NOT NULL,
          member_id           INT UNSIGNED        NOT NULL,
          discriminator_type VARCHAR(255)         NULL,
          updated_at        DATETIME             NULL,
          created_at        DATETIME             NULL)');

//        $this->addSql('ALTER TABLE dtb_store_member ADD CONSTRAINT fk_store_id_member FOREIGN KEY (store_id) REFERENCES dtb_store(id)');
//        $this->addSql('ALTER TABLE dtb_store_member ADD CONSTRAINT fk_store_member_id FOREIGN KEY (member_id) REFERENCES dtb_member(id)');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
//        $this->addSql('DROP TABLE dtb_store_member');
    }
}
