<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201220172105 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dtb_product_video (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            product_id          INT unsigned    NULL ,
            creator_id          INT unsigned    NULL,
            video_name          VARCHAR(255)    NOT NULL,
            video_link          VARCHAR(255)    NOT NULL,
            video_price         INT             NOT NULL,
            is_use              INT             NOT NULL DEFAULT 1,
            discriminator_type  VARCHAR(255)    NULL,
            create_date         DATETIME        NULL)');

//        $this->addSql('ALTER TABLE dtb_product_video ADD CONSTRAINT FK_3x FOREIGN KEY (product_id) REFERENCES dtb_product(id)');
//        $this->addSql('ALTER TABLE dtb_product_video ADD CONSTRAINT FK_3xss FOREIGN KEY (creator_id) REFERENCES dtb_member(id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dtb_product_video');
    }
}

