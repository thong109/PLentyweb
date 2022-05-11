<?php
/**
 * This file is part of Stripe4
 *
 * Copyright(c) Akira Kurozumi <info@a-zumi.net>
 *
 * https://a-zumi.net
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Stripe4\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Entity\Customer;

if (!class_exists(CreditCard::class)) {
    /**
     * Class CreditCard
     * @package Plugin\Stripe4\Entity
     *
     * @ORM\Table(name="plg_stripe_credit_card")
     * @ORM\Entity(repositoryClass="Plugin\Stripe4\Repository\CreditCardRepository")
     */
    class CreditCard
    {
        /**
         * @var int
         *
         * @ORM\Column(name="id", type="integer", options={"unsigned": true})
         * @ORM\Id()
         * @ORM\GeneratedValue(strategy="IDENTITY")
         */
        private $id;

        /**
         * @var Customer
         *
         * @ORM\ManyToOne(targetEntity="Eccube\Entity\Customer", inversedBy="creditCards")
         */
        private $Customer;

        /**
         * @var string
         *
         * @ORM\Column(type="string")
         */
        private $stripe_customer_id;

        /**
         * @var string
         *
         * @ORM\Column(type="string")
         */
        private $stripe_payment_method_id;

        /**
         * @var string
         *
         * @ORM\Column(type="string")
         */
        private $fingerprint;

        /**
         * @var string
         *
         * @ORM\Column(type="string")
         */
        private $brand;

        /**
         * @var string
         *
         * @ORM\Column(type="string")
         */
        private $last4;

        /**
         * @return int
         */
        public function getId(): int
        {
            return $this->id;
        }

        /**
         * @return Customer
         */
        public function getCustomer(): Customer
        {
            return $this->Customer;
        }

        /**
         * @param Customer $customer
         * @return $this
         */
        public function setCustomer(Customer $customer): self
        {
            $this->Customer = $customer;

            return $this;
        }

        /**
         * @return string
         */
        public function getStripeCustomerId(): string
        {
            return $this->stripe_customer_id;
        }

        /**
         * @param string $stripe_customer_id
         * @return $this
         */
        public function setStripeCustomerId(string $stripe_customer_id): self
        {
            $this->stripe_customer_id = $stripe_customer_id;

            return $this;
        }

        /**
         * @return string
         */
        public function getStripePaymentMethodId(): string
        {
            return $this->stripe_payment_method_id;
        }

        /**
         * @param string $stripe_payment_method_id
         * @return $this
         */
        public function setStripePaymentMethodId(string $stripe_payment_method_id): self
        {
            $this->stripe_payment_method_id = $stripe_payment_method_id;

            return $this;
        }

        /**
         * @return string
         */
        public function getFingerprint(): string
        {
            return $this->fingerprint;
        }

        /**
         * @param string $fingerprint
         * @return $this
         */
        public function setFingerprint(string $fingerprint): self
        {
            $this->fingerprint = $fingerprint;

            return $this;
        }

        /**
         * @return string
         */
        public function getBrand(): string
        {
            return $this->brand;
        }

        /**
         * @param string $brand
         * @return $this
         */
        public function setBrand(string $brand): self
        {
            $this->brand = $brand;

            return $this;
        }

        /**
         * @return string
         */
        public function getLast4(): string
        {
            return $this->last4;
        }

        /**
         * @param string $last4
         * @return $this
         */
        public function setLast4(string $last4): self
        {
            $this->last4 = $last4;

            return $this;
        }
    }
}
