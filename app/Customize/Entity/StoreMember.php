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

if (!class_exists('\Customize\Entity\StoreMember')) {
    /**
     * Shop
     *
     * @ORM\Table(name="dtb_store_member")
     * @ORM\InheritanceType("SINGLE_TABLE")
     * @ORM\DiscriminatorColumn(name="discriminator_type", type="string", length=255)
     * @ORM\HasLifecycleCallbacks()
     * @ORM\Entity(repositoryClass="Customize\Repository\StoreMemberRepository")
     */
    class StoreMember extends \Eccube\Entity\AbstractEntity
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
         * @ORM\Column(name="name", type="string", length=255)
         */

        /**
         * @var \DateTime
         *
         * @ORM\Column(name="created_at", type="datetimetz")
         */
        private $create_date;

        /**
         * @var \DateTime
         *
         * @ORM\Column(name="updated_at", type="datetimetz")
         */
        private $update_date;


        /**
         * @var \Eccube\Entity\Member
         *
         * @ORM\ManyToOne(targetEntity="Eccube\Entity\Member")
         * @ORM\JoinColumns({
         *   @ORM\JoinColumn(name="member_id", referencedColumnName="id")
         * })
         */
        private $Member;

        /**
         * @var \Customize\Entity\Store
         *
         * @ORM\ManyToOne(targetEntity="Customize\Entity\Store")
         * @ORM\JoinColumns({
         *   @ORM\JoinColumn(name="store_id", referencedColumnName="id")
         * })
         */
        private $Store;

        /**
         * @return int
         */
        public function getId(): int
        {
            return $this->id;
        }

        /**
         * @param int $id
         */
        public function setId(int $id): void
        {
            $this->id = $id;
        }

        /**
         * @return \DateTime
         */
        public function getCreateDate(): \DateTime
        {
            return $this->create_date;
        }

        /**
         * @param \DateTime $create_date
         */
        public function setCreateDate(\DateTime $create_date): void
        {
            $this->create_date = $create_date;
        }

        /**
         * @return \DateTime
         */
        public function getUpdateDate(): \DateTime
        {
            return $this->update_date;
        }

        /**
         * @param \DateTime $update_date
         */
        public function setUpdateDate(\DateTime $update_date): void
        {
            $this->update_date = $update_date;
        }

        /**
         * @return \Eccube\Entity\Member
         */
        public function getMember(): \Eccube\Entity\Member
        {
            return $this->Member;
        }

        /**
         * @param \Eccube\Entity\Member $Member
         */
        public function setMember(\Eccube\Entity\Member $Member): void
        {
            $this->Member = $Member;
        }



        /**
         * Get store.
         *
         * @return Store|null
         */
        public function getStore()
        {
            return $this->Store;
        }


        /**
         * @param Store $Store
         */
        public function setStore(Store $Store): void
        {
            $this->Store = $Store;
        }


        /**
         * Constructor
         */
        public function __construct()
        {
        }
    }
}
