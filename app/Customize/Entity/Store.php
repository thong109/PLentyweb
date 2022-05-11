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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Mapping\ClassMetadata;

if (!class_exists('\Customize\Entity\Store')) {
    /**
     * Shop
     *
     * @ORM\Table(name="dtb_store")
     * @ORM\InheritanceType("SINGLE_TABLE")
     * @ORM\DiscriminatorColumn(name="discriminator_type", type="string", length=255)
     * @ORM\HasLifecycleCallbacks()
     * @ORM\Entity(repositoryClass="Customize\Repository\StoreRepository")
     */
    class Store extends \Eccube\Entity\AbstractEntity
    {
        public static function loadValidatorMetadata(ClassMetadata $metadata)
        {
            $metadata->addConstraint(new UniqueEntity([
                'fields' => 'mail_send',
                'message' => 'form_error.customer_already_exists',
            ]));

            $metadata->addConstraint(new UniqueEntity([
                'fields' => 'mail_receive_error',
                'message' => 'form_error.customer_already_exists',
            ]));

            $metadata->addConstraint(new UniqueEntity([
                'fields' => 'mail_contact',
                'message' => 'form_error.customer_already_exists',
            ]));

            $metadata->addConstraint(new UniqueEntity([
                'fields' => 'mail_feed_back',
                'message' => 'form_error.customer_already_exists',
            ]));
        }

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
         * @ORM\Column(name="name", type="string", length=255)
         */
        private $name;

        /**
         * @var string
         *
         * @ORM\Column(name="name_kana", type="string", length=255, nullable=true)
         */
        private $name_kana;

        /**
         * @var string
         *
         * @ORM\Column(name="name_sign", type="string", length=255, nullable=true)
         */
        private $name_sign;

        /**
         * @var string
         *
         * @ORM\Column(name="company_name", type="string", length=255, nullable=true)
         */
        private $company_name;

        /**
         * @var string
         *
         * @ORM\Column(name="company_name_kana", type="string", length=255, nullable=true)
         */
        private $company_name_kana;

        /**
         * @var string
         *
         * @ORM\Column(name="postal_code", type="string", length=8, nullable=true)
         */
        private $postal_code;

        /**
         * @var string
         *
         * @ORM\Column(name="image", type="string", length=255, nullable=true)
         */
        private $image;

        /**
         * @var string
         *
         * @ORM\Column(name="mail_contact", type="string", length=255, nullable=true)
         */
        private $mail_contact;

        /**
         * @var string
         *
         * @ORM\Column(name="mail_feedback", type="string", length=255)
         */
        private $mail_feed_back;

        /** @var string
         *
         * @ORM\Column(name="mail_receive_error", type="string", length=255)
         */
        private $mail_receive_error;

        /**
         * @var string
         *
         * @ORM\Column(name="mail_send", type="string", length=255)
         */
        private $mail_send;

        /**
         * @var string|null
         *
         * @ORM\Column(name="description", type="string", length=4000, nullable=true)
         */
        private $description;

        /**
         * @var string|null
         *
         * @ORM\Column(name="phone_number", type="string", length=14, nullable=true)
         */
        private $phone_number;

        /**
         * @var \DateTime
         *
         * @ORM\Column(name="create_date", type="datetimetz")
         */
        private $create_date;

        /**
         * @var \DateTime
         *
         * @ORM\Column(name="update_date", type="datetimetz")
         */
        private $update_date;

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
         * @var \Eccube\Entity\Master\Pref
         *
         * @ORM\ManyToOne(targetEntity="Eccube\Entity\Master\Pref")
         * @ORM\JoinColumns({
         *   @ORM\JoinColumn(name="pref_id", referencedColumnName="id")
         * })
         */
        private $Pref;

        /**
         * @var string|null
         *
         * @ORM\Column(name="addr01", type="string", length=255, nullable=true)
         */
        private $addr01;

        /**
         * @var string|null
         *
         * @ORM\Column(name="addr02", type="string", length=255, nullable=true)
         */
        private $addr02;


        /**
         * @var \Doctrine\Common\Collections\Collection
         *
         * @ORM\ManyToMany(targetEntity="Customize\Entity\Store", mappedBy="store")
         */
        private $Member;


        public function getId(): int
        {
            return $this->id;
        }

        /**
         * @return \Doctrine\Common\Collections\Collection
         */
        public function getMember(): \Doctrine\Common\Collections\Collection
        {
            return $this->Member;
        }

        /**
         * @param \Doctrine\Common\Collections\Collection $Member
         */
        public function setMember(\Doctrine\Common\Collections\Collection $Member): void
        {
            $this->Member = $Member;
        }

        public function setId(int $id): void
        {
            $this->id = $id;
        }

        /**
         * @return string
         */
        public function getName(): ?string
        {
            return $this->name;
        }

        public function setName(string $name): void
        {
            $this->name = $name;
        }

        /**
         * @return string
         */
        public function getNameKana(): ?string
        {
            return $this->name_kana;
        }

        public function setNameKana(string $name_kana): void
        {
            $this->name_kana = $name_kana;
        }

        /**
         * @return string
         */
        public function getNameSign(): ?string
        {
            return $this->name_sign;
        }

        public function setNameSign(string $name_sign): void
        {
            $this->name_sign = $name_sign;
        }

        /**
         * @return string
         */
        public function getCompanyName(): ?string
        {
            return $this->company_name;
        }

        public function setCompanyName(string $company_name): void
        {
            $this->company_name = $company_name;
        }

        /**
         * @return string
         */
        public function getPostalCode(): ?string
        {
            return $this->postal_code;
        }

        public function setPostalCode(string $postal_code): void
        {
            $this->postal_code = $postal_code;
        }

        /**
         * @return string
         */
        public function getCompanyNameKana(): ?string
        {
            return $this->company_name_kana;
        }

        public function setCompanyNameKana(string $company_name_kana): void
        {
            $this->company_name_kana = $company_name_kana;
        }

        /**
         * @return string
         */
        public function getImage(): ?string
        {
            return $this->image;
        }

        public function setImage(string $image): void
        {
            $this->image = $image;
        }

        /**
         * @return string
         */
        public function getMailContact(): ?string
        {
            return $this->mail_contact;
        }

        public function setMailContact(string $mail_contact): void
        {
            $this->mail_contact = $mail_contact;
        }

        /**
         * @return string
         */
        public function getMailFeedBack(): ?string
        {
            return $this->mail_feed_back;
        }

        public function setMailFeedBack(string $mail_feed_back): void
        {
            $this->mail_feed_back = $mail_feed_back;
        }

        /**
         * @return string
         */
        public function getMailReceiveError(): ?string
        {
            return $this->mail_receive_error;
        }

        public function setMailReceiveError(string $mail_receive_error): void
        {
            $this->mail_receive_error = $mail_receive_error;
        }

        /**
         * @return string
         */
        public function getMailSend(): ?string
        {
            return $this->mail_send;
        }

        public function setMailSend(string $mail_send): void
        {
            $this->mail_send = $mail_send;
        }

        public function getDescription(): ?string
        {
            return $this->description;
        }

        public function setDescription(?string $description): void
        {
            $this->description = $description;
        }

        public function getPhoneNumber(): ?string
        {
            return $this->phone_number;
        }

        public function setPhoneNumber(?string $phone_number): void
        {
            $this->phone_number = $phone_number;
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

        public function setCreateDate(\DateTime $create_date): void
        {
            $this->create_date = $create_date;
        }

        /**
         * Get updateDate.
         *
         * @return \DateTime
         */
        public function getUpdateDate()
        {
            return $this->update_date;
        }

        public function setUpdateDate(\DateTime $update_date): void
        {
            $this->update_date = $update_date;
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

        /**
         * Set creator.
         *
         * @return Store
         */
        public function setCreator(\Eccube\Entity\Member $creator = null)
        {
            $this->Creator = $creator;

            return $this;
        }

        /**
         * Get pref.
         *
         * @return \Eccube\Entity\Master\Pref|null
         */
        public function getPref()
        {
            return $this->Pref;
        }

        public function setPref(\Eccube\Entity\Master\Pref $Pref): void
        {
            $this->Pref = $Pref;
        }

        public function getAddr01(): ?string
        {
            return $this->addr01;
        }

        public function setAddr01(?string $addr01): void
        {
            $this->addr01 = $addr01;
        }

        public function getAddr02(): ?string
        {
            return $this->addr02;
        }

        public function setAddr02(?string $addr02): void
        {
            $this->addr02 = $addr02;
        }

        /**
         * Constructor
         */
        public function __construct()
        {
        }
    }
}
