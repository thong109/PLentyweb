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
 * @author vungpv93@gmail.com
 * @Make Seminar Database.
 */

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210114001424 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            CREATE TABLE `dtb_product_seminar` (
                `id`                  int(11) NOT NULL AUTO_INCREMENT,
                `product_id`          int(11) NOT NULL,
                `zoom_id`             bigint(20) DEFAULT NULL,
                `zoom_password`       VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                `link`                VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                `topic`               VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                `start_time`          timestamp NULL DEFAULT NULL,
                `duration`            time DEFAULT NULL,
                `timezone`            varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
                `calendar`            tinyint(1) DEFAULT 0 NULL,
                `host_video`          tinyint(1) DEFAULT 0 NULL,
                `participant_video`   tinyint(1) DEFAULT NULL,
                `approval_type`       int(11) DEFAULT NULL,
                `audio`               tinyint(1) DEFAULT 0 NULL,
                `auto_recording`      tinyint(1) DEFAULT 0 NULL,
                `join_before_host`    tinyint(1) DEFAULT 0 NULL,
                `mute_upon_entry`     tinyint(1) DEFAULT 0 NULL,
                `waiting_room`        tinyint(1) DEFAULT 0 NULL,
                `create_date`         datetime DEFAULT NULL,
                `update_date`         datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');

        $this->addSql('ALTER TABLE dtb_product_seminar ADD CONSTRAINT FK_seminar_product_id FOREIGN KEY (product_id) REFERENCES dtb_product(id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE `dtb_product_seminar`');
    }
}
