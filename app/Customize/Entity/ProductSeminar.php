<?php

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

namespace Customize\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Product;

if (!class_exists('\Customize\Entity\ProductSeminar')) {
    /**
     * ProductSeminar
     *
     * @ORM\Table(name="dtb_product_seminar")
     * @ORM\InheritanceType("SINGLE_TABLE")
     * @ORM\DiscriminatorColumn(name="discriminator_type", type="string", length=255)
     * @ORM\HasLifecycleCallbacks()
     * @ORM\Entity(repositoryClass="Customize\Repository\ProductSeminarRepository")
     */
    class ProductSeminar extends \Eccube\Entity\AbstractEntity
    {
        /**
         * @var int
         *
         * @ORM\Column(name="id", type="integer", options={"unsigned":true})
         * @ORM\Id
         * @ORM\GeneratedValue(strategy="IDENTITY")
         */
        private $id;

        /**
         * @var int
         *
         * @ORM\Column(name="zoom_id", type="bigint")
         */
        private $zoomId;

        /**
         * @var string
         *
         * @ORM\Column(name="zoom_password", type="string", length=255)
         */
        private $zoomPassword;

        /**
         * @var string
         *
         * @ORM\Column(name="join_url", type="string", length=255)
         */
        private $joinUrl;

        /**
         * @var DateTime
         *
         * @ORM\Column(name="start_time", type="datetimetz")
         */
        private $startTime;

        /**
         * @var int
         *
         * @ORM\Column(name="duration", type="integer")
         */
        private $duration;

        /**
         * @var DateTime
         *
         * @ORM\Column(name="create_date", type="datetimetz")
         */
        private $createDate;

        /**
         * @var DateTime
         *
         * @ORM\Column(name="update_date", type="datetimetz")
         */
        private $updateDate;

        /**
         * @var Product
         *
         * @ORM\ManyToOne(targetEntity="Eccube\Entity\Product", inversedBy="ProductSeminar")
         * @ORM\JoinColumns({
         *   @ORM\JoinColumn(name="product_id", referencedColumnName="id")
         * })
         */
        private $product;

        /**
         * Get id.
         */
        public function getId(): int
        {
            return $this->id;
        }

        /**
         * Set zoomId.
         *
         * @param $zoomId
         */
        public function setZoomId($zoomId): ProductSeminar
        {
            $this->zoomId = $zoomId;

            return $this;
        }

        /**
         * Get zoomId.
         */
        public function getZoomId(): int
        {
            return $this->zoomId;
        }

        /**
         * Set zoomPassword.
         *
         * @param $zoomPassword
         */
        public function setZoomPassword($zoomPassword): ProductSeminar
        {
            $this->zoomPassword = $zoomPassword;

            return $this;
        }

        /**
         * Get zoomPassword.
         */
        public function getZoomPassword(): string
        {
            return $this->zoomPassword;
        }

        /**
         * Set start_time.
         *
         * @param $startTime
         */
        public function setStartTime($startTime): ProductSeminar
        {
            $this->startTime = $startTime;

            return $this;
        }

        /**
         * Get start_time.
         */
        public function getStartTime(): DateTime
        {
            return $this->startTime;
        }

        /**
         * Set duration.
         *
         * @param $duration
         */
        public function setDuration($duration): ProductSeminar
        {
            $this->duration = $duration;

            return $this;
        }

        /**
         * Get duration.
         */
        public function getDuration(): int
        {
            return $this->duration;
        }

        /**
         * Set create_date.
         *
         * @param $createDate
         */
        public function setCreateDate($createDate): ProductSeminar
        {
            $this->createDate = $createDate;

            return $this;
        }

        /**
         * Get create_date.
         */
        public function getCreateDate(): datetime
        {
            return $this->createDate;
        }

        /**
         * Set update_date.
         *
         * @param $updateDate
         */
        public function setUpdateDate($updateDate): ProductSeminar
        {
            $this->updateDate = $updateDate;

            return $this;
        }

        /**
         * Get update_date.
         */
        public function getUpdateDate(): datetime
        {
            return $this->updateDate;
        }

        /**
         * Set product.
         */
        public function setProduct(Product $product = null): ProductSeminar
        {
            $this->product = $product;

            return $this;
        }

        /**
         * Get product.
         */
        public function getProduct(): ?Product
        {
            return $this->product;
        }

        /**
         * Set Join_url.
         *
         * @param $joinUrl
         */
        public function setJoinUrl($joinUrl): ProductSeminar
        {
            $this->joinUrl = $joinUrl;

            return $this;
        }

        /**
         * Get Join_url.
         */
        public function getJoinUrl(): string
        {
            return $this->joinUrl;
        }
    }
}
