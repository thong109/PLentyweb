<?php

declare(strict_types=1);

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201214172105 extends AbstractMigration
{

    const NAME = 'dtb_store';

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable(self::NAME)) {
            return;
        }
        $this->addSql('CREATE TABLE dtb_store (
            id                  INT AUTO_INCREMENT PRIMARY KEY,
            pref_id             SMALLINT(5) UNSIGNED NOT NULL,
            creator_id          INT UNSIGNED         NOT NULL,
            company_name        VARCHAR(255)         NULL,
            company_name_kana   VARCHAR(255)         NULL,
            name                VARCHAR(255)         NOT NULL,
            name_kana           VARCHAR(255)         NOT NULL,
            name_sign           VARCHAR(255)         NULL,
            addr01              VARCHAR(255)         NOT NULL,
            addr02              VARCHAR(255)         NULL,
            mail_contact        VARCHAR(255)         NULL,
            mail_receive_error  VARCHAR(255)         NOT NULL,
            mail_send           VARCHAR(255)         NOT NULL,
            mail_feedback       VARCHAR(255)         NULL,
            phone_number        VARCHAR(25)          NULL,
            description         TEXT                 NULL,
            postal_code         VARCHAR(25)          NULL,
            image               VARCHAR(255)         NULL,
            discriminator_type  VARCHAR(255)         NULL,
            update_date         DATETIME             NULL,
            create_date         DATETIME             NULL)');

//        $this->addSql('ALTER TABLE dtb_store ADD CONSTRAINT fk_creator_id_store FOREIGN KEY (creator_id) REFERENCES dtb_member(id)');
//        $this->addSql('ALTER TABLE dtb_store ADD CONSTRAINT fk_pref_id FOREIGN KEY (pref_id) REFERENCES mtb_pref(id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dtb_store');
    }
}
