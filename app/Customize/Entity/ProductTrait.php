<?php

namespace Customize\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Product")
 */
trait ProductTrait
{
    /**
     * @ORM\Column(name="ec_link",type="string", nullable=true)
     */
    public $ec_link;

    /**
     * @return mixed
     */
    public function getRentalMin()
    {
        return $this->rental_min;
    }

    /**
     * @param mixed $rental_min
     */
    public function setRentalMin($rental_min): void
    {
        $this->rental_min = $rental_min;
    }


    /**
     * @ORM\Column(name="rental_min_day",type="integer", nullable=true)
     */
    public $rental_min;


    /**
     * @ORM\Column(name="highlight", type="boolean", options={"default":true})
     */
    public $highlight = true;

    /**
     * @return mixed
     */
    public function getHighlight()
    {
        return $this->highlight;
    }

    /**
     * @param mixed $highlight
     */
    public function setHighlight($highlight): void
    {
        $this->highlight = $highlight;
    }

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
     * @return Store
     */
    public function getStore(): Store
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
     * Get ecLink.
     *
     * @return string
     */
    public function getEcLink()
    {
        return $this->ec_link;
    }

    /**
     * Set ecLink.
     *
     * @param string $ecLink
     *
     */
    public function setEcLink($ecLink)
    {
        $this->ec_link = $ecLink;

        return $this;
    }



}
