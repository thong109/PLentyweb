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

namespace Eccube\Entity;

use Doctrine\ORM\Mapping as ORM;

if (!class_exists('\Eccube\Entity\CartItem')) {
    /**
     * CartItem
     *
     * @ORM\Table(name="dtb_cart_item")
     * @ORM\InheritanceType("SINGLE_TABLE")
     * @ORM\DiscriminatorColumn(name="discriminator_type", type="string", length=255)
     * @ORM\HasLifecycleCallbacks()
     * @ORM\Entity(repositoryClass="Eccube\Repository\CartItemRepository")
     */
    class CartItem extends \Eccube\Entity\AbstractEntity implements ItemInterface
    {
        use PointRateTrait;

        /**
         * @var integer
         *
         * @ORM\Column(name="id", type="integer", options={"unsigned":true})
         * @ORM\Id
         * @ORM\GeneratedValue(strategy="IDENTITY")
         */
        private $id;

        /**
         * @var string
         *
         * @ORM\Column(name="price", type="decimal", precision=12, scale=2, options={"default":0})
         */
        private $price = 0;

        /**
         * @var string
         *
         * @ORM\Column(name="quantity", type="decimal", precision=10, scale=0, options={"default":0})
         */
        private $quantity = 0;

        /**
         * @var string
         *
         * @ORM\Column(name="rental_min_day", type="decimal", precision=10, scale=0, options={"default":1})
         */
        private $rental_min_day = 0;


        /**
         * @var \DateTime|null
         *
         * @ORM\Column(name="rental_start_date", type="datetimetz")
         */
        private $rental_start_date;


        /**
         * @var string
         *
         * @ORM\Column(name="schedule_id", type="integer")
         */
        private $scheduleId = 0;

        /**
         * @var \Eccube\Entity\ProductClass
         *
         * @ORM\ManyToOne(targetEntity="Eccube\Entity\ProductClass")
         * @ORM\JoinColumns({
         *   @ORM\JoinColumn(name="product_class_id", referencedColumnName="id")
         * })
         */
        private $ProductClass;

        /**
         * @var \Eccube\Entity\Cart
         *
         * @ORM\ManyToOne(targetEntity="Eccube\Entity\Cart", inversedBy="CartItems")
         * @ORM\JoinColumns({
         *   @ORM\JoinColumn(name="cart_id", referencedColumnName="id", onDelete="CASCADE")
         * })
         */
        private $Cart;

        /**
         * session?????????????????????????????????????????????
         *
         * @var int
         */
        private $product_class_id;

        public function __sleep()
        {
            return ['product_class_id', 'price', 'quantity'];
        }

        /**
         * @return int
         */
        public function getId()
        {
            return $this->id;
        }

        /**
         * @param  integer  $price
         *
         * @return CartItem
         */
        public function setPrice($price)
        {
            $this->price = $price;

            return $this;
        }

        /**
         * @return string
         */
        public function getPrice()
        {
            return $this->price;
        }

        /**
         * @param  integer  $scheduleId
         *
         * @return CartItem
         */
        public function setScheduleId($scheduleId)
        {
            $this->scheduleId = $scheduleId;

            return $this;
        }

        /**
         * @return string
         */
        public function getScheduleId()
        {
            return $this->scheduleId;
        }

        /**
         * @param  integer  $quantity
         *
         * @return CartItem
         */
        public function setQuantity($quantity)
        {
            $this->quantity = $quantity;

            return $this;
        }

        /**
         * @return string
         */
        public function getQuantity()
        {
            return $this->quantity;
        }


        /**
         * @return string
         */
        public function getRentalMinDay()
        {
            return $this->rental_min_day;
        }

        /**
         * @param string $rental_min_day
         */
        public function setRentalMinDay(string $rental_min_day): void
        {
            $this->rental_min_day = $rental_min_day;
        }


        /**
         * Set Rental Start Date.
         *
         * @param \DateTime|null $birth
         *
         * @return CartItem
         */
        public function setRentalStartDate($rental_start_date = null)
        {
            $this->rental_start_date = $rental_start_date;

            return $this;
        }

        /**
         * Get Rental Start Date.
         *
         */
        public function getRentalStartDate()
        {
            return $this->rental_start_date;
        }

        /**
         * @return integer
         */
        public function getTotalPrice()
        {
            return $this->getPrice() * $this->getQuantity();
        }

        /**
         * ????????????????????????.
         *
         * @return boolean ????????????????????? true
         */
        public function isProduct()
        {
            return true;
        }

        /**
         * ????????????????????????.
         *
         * @return boolean ????????????????????? true
         */
        public function isDeliveryFee()
        {
            return false;
        }

        /**
         * ???????????????????????????.
         *
         * @return boolean ???????????????????????? true
         */
        public function isCharge()
        {
            return false;
        }

        /**
         * ???????????????????????????.
         *
         * @return boolean ???????????????????????? true
         */
        public function isDiscount()
        {
            return false;
        }

        /**
         * ????????????????????????.
         *
         * @return boolean ????????????????????? true
         */
        public function isTax()
        {
            return false;
        }

        /**
         * ??????????????????????????????.
         *
         * @return boolean ??????????????????????????? true
         */
        public function isPoint()
        {
            return false;
        }

        public function getOrderItemType()
        {
            // TODO OrderItemType::PRODUCT
            $ItemType = new \Eccube\Entity\Master\OrderItemType();

            return $ItemType;
        }

        /**
         * @return $this
         */
        public function setProductClass(ProductClass $ProductClass)
        {
            $this->ProductClass = $ProductClass;

            $this->product_class_id = is_object($ProductClass) ?
            $ProductClass->getId() : null;

            return $this;
        }

        /**
         * @return ProductClass
         */
        public function getProductClass()
        {
            return $this->ProductClass;
        }

        /**
         * @return int|null
         */
        public function getProductClassId()
        {
            return $this->product_class_id;
        }

        public function getPriceIncTax()
        {
            // TODO ItemInterface?????????, Cart::price??????????????????????????????????????????,??????????????????????????????????????????
            return $this->price;
        }

        /**
         * @return Cart
         */
        public function getCart()
        {
            return $this->Cart;
        }

        public function setCart(Cart $Cart)
        {
            $this->Cart = $Cart;
        }
    }
}
