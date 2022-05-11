<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210105072105 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO mtb_sale_type (id, name, sort_no, discriminator_type) VALUES (10, 'Normal', 1, 'saletype')");
        $this->addSql("INSERT INTO mtb_sale_type (id, name, sort_no, discriminator_type) VALUES (20, 'Rental', 2, 'saletype')");
        $this->addSql("INSERT INTO mtb_sale_type (id, name, sort_no, discriminator_type) VALUES (30, 'Video', 3, 'saletype')");
        $this->addSql("INSERT INTO mtb_sale_type (id, name, sort_no, discriminator_type) VALUES (40, 'Ec-Link', 4, 'saletype')");
        $this->addSql("INSERT INTO mtb_sale_type (id, name, sort_no, discriminator_type) VALUES (50, 'Seminar', 5, 'saletype')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mtb_sale_type');
    }
}

