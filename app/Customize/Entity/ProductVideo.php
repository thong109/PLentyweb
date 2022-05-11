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

use Doctrine\ORM\Mapping as ORM;

if (!class_exists('\Customize\Entity\ProductVideo')) {
    /**
     * ProductVideo
     *
     * @ORM\Table(name="dtb_product_video")
     * @ORM\InheritanceType("SINGLE_TABLE")
     * @ORM\DiscriminatorColumn(name="discriminator_type", type="string", length=255)
     * @ORM\HasLifecycleCallbacks()
     * @ORM\Entity(repositoryClass="Customize\Repository\ProductVideoRepository")
     */
    class ProductVideo extends \Eccube\Entity\AbstractEntity
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
         * @var string
         *
         * @ORM\Column(name="video_link", type="string", length=255)
         */
        private $video_link;

        /**
         * @var string
         *
         * @ORM\Column(name="video_name", type="string", length=255)
         */
        private $video_name;

        /**
         * @var int
         *
         * @ORM\Column(name="video_price", type="integer", options={"unsigned":true})
         */
        private $video_price;

        /**
         * @var int
         *
         * @ORM\Column(name="in_use", type="integer")
         */
        private $in_use;

        /**
         * @var \DateTime
         *
         * @ORM\Column(name="create_date", type="datetimetz")
         */
        private $create_date;

        /**
         * @var \Eccube\Entity\Product
         *
         * @ORM\ManyToOne(targetEntity="Eccube\Entity\Product", inversedBy="ProductVideo")
         * @ORM\JoinColumns({
         *   @ORM\JoinColumn(name="product_id", referencedColumnName="id")
         * })
         */
        private $Product;

        /**
         * @var \Eccube\Entity\Member
         *
         * @ORM\ManyToOne(targetEntity="Eccube\Entity\Member")
         * @ORM\JoinColumns({
         *   @ORM\JoinColumn(name="creator_id", referencedColumnName="id")
         * })
         */
        private $Creator;

        /**
         * Get id.
         *
         * @return int
         */
        public function getId()
        {
            return $this->id;
        }

        /**
         * Set videoLink.
         *
         * @param string $videoLink
         *
         * @return ProductVideo
         */
        public function setVideoLink($videoLink)
        {
            $this->video_link = $videoLink;

            return $this;
        }

        /**
         * Get videoLink.
         *
         * @return string
         */
        public function getVideoLink()
        {
            return $this->video_link;
        }

        /**
         * Set videoName.
         *
         * @param string $videoName
         *
         * @return ProductVideo
         */
        public function setVideoName($videoName)
        {
            $this->video_name = $videoName;

            return $this;
        }

        /**
         * Get videoName.
         *
         * @return string
         */
        public function getVideoName() : ?string
        {
            return $this->video_name;
        }

        /**
         * Set videoPrice.
         *
         * @param int $videoPrice
         *
         * @return ProductVideo
         */
        public function setVideoPrice($videoPrice)
        {
            $this->video_price = $videoPrice;

            return $this;
        }

        /**
         * Get videoPrice.
         *
         * @return int
         */
        public function getVideoPrice(): ?int
        {
            return $this->video_price;
        }

        /**
         * Set inUse.
         *
         * @param int $inUse
         *
         * @return ProductVideo
         */
        public function setInUse($inUse)
        {
            $this->in_use = $inUse;

            return $this;
        }

        /**
         * Get inUse.
         *
         * @return int
         */
        public function getInUse(): ?int
        {
            return $this->in_use;
        }

        /**
         * Set createDate.
         *
         * @param \DateTime $createDate
         *
         * @return ProductVideo
         */
        public function setCreateDate($createDate)
        {
            $this->create_date = $createDate;

            return $this;
        }

        /**
         * Get createDate.
         *
         * @return \DateTime
         */
        public function getCreateDate()
        {
            return $this->create_date;
        }

        /**
         * Set product.
         *
         * @param \Eccube\Entity\Product|null $product
         *
         * @return ProductVideo
         */
        public function setProduct(\Eccube\Entity\Product $product = null)
        {
            $this->Product = $product;

            return $this;
        }

        /**
         * Get product.
         *
         * @return \Eccube\Entity\Product|null
         */
        public function getProduct()
        {
            return $this->Product;
        }

        /**
         * Set creator.
         *
         * @param \Eccube\Entity\Member|null $creator
         *
         * @return ProductVideo
         */
        public function setCreator(\Eccube\Entity\Member $creator = null)
        {
            $this->Creator = $creator;

            return $this;
        }

        /**
         * Get creator.
         *
         * @return \Eccube\Entity\Member|null
         */
        public function getCreator()
        {
            return $this->Creator;
        }
    }
}
