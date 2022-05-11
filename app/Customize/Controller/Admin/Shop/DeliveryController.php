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

namespace Customize\Controller\Admin\Shop;

use Doctrine\Common\Collections\ArrayCollection;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Delivery;
use Eccube\Entity\DeliveryFee;
use Eccube\Entity\DeliveryTime;
use Eccube\Entity\PaymentOption;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Admin\DeliveryType;
use Eccube\Repository\DeliveryFeeRepository;
use Eccube\Repository\DeliveryRepository;
use Eccube\Repository\DeliveryTimeRepository;
use Eccube\Repository\Master\PrefRepository;
use Eccube\Repository\Master\SaleTypeRepository;
use Eccube\Repository\PaymentOptionRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Customize\Repository\StoreMemberRepository;

/**
 * Class DeliveryController
 */
class DeliveryController extends AbstractController
{
    /**
     * @var PaymentOptionRepository
     */
    protected $paymentOptionRepository;

    /**
     * @var DeliveryFeeRepository
     */
    protected $deliveryFeeRepository;

    /**
     * @var PrefRepository
     */
    protected $prefRepository;

    /**
     * @var DeliveryRepository
     */
    protected $deliveryRepository;

    /**
     * @var DeliveryTimeRepository
     */
    protected $deliveryTimeRepository;

    /**
     * @var DeliveryTimeRepository
     */
    protected $saleTypeRepository;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var StoreMemberRepository
     */
    protected $storeMemberRepository;

    /**
     * DeliveryController constructor.
     */
    public function __construct(
        PaymentOptionRepository $paymentOptionRepository,
        DeliveryFeeRepository $deliveryFeeRepository,
        PrefRepository $prefRepository,
        DeliveryRepository $deliveryRepository,
        DeliveryTimeRepository $deliveryTimeRepository,
        SaleTypeRepository $saleTypeRepository,
        TokenStorageInterface $tokenStorage,
        StoreMemberRepository $storeMemberRepository
    ) {
        $this->paymentOptionRepository = $paymentOptionRepository;
        $this->deliveryFeeRepository = $deliveryFeeRepository;
        $this->prefRepository = $prefRepository;
        $this->deliveryRepository = $deliveryRepository;
        $this->deliveryTimeRepository = $deliveryTimeRepository;
        $this->saleTypeRepository = $saleTypeRepository;
        $this->tokenStorage = $tokenStorage;
        $this->storeMemberRepository = $storeMemberRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/setting/shop/delivery", name="admin_setting_shop_delivery")
     * @Template("@admin/Setting/Shop/delivery.twig")
     */
    public function index(Request $request)
    {

        $LoginMember = clone $this->tokenStorage->getToken()->getUser();
        $data = $this->storeMemberRepository->findBy(array('Member' => $LoginMember));

        $Deliveries = $this->deliveryRepository
            ->findBy([], ['sort_no' => 'DESC']);

        if($data){
            $Store = $data[0]->getStore()->getId();
            foreach($Deliveries as $key => $value){
                $dataDelivery = $value->getStoreDelivery()->getValues();
                if($dataDelivery && ($dataDelivery[0]->getStore()->getId() === $Store)){
                    continue;
                }else{
                    unset($Deliveries[$key]);
                }
            }
        }


        $event = new EventArgs(
            [
                'Deliveries' => $Deliveries,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_SETTING_SHOP_DELIVERY_INDEX_COMPLETE, $event);

        return [
            'Deliveries' => $Deliveries,
        ];
    }

    public function findOneByIdJoinedToCategory($productId)
    {
        $query = $this->getEntityManager()
            ->createQuery(
                'SELECT p, c FROM AppBundle:Product p
        JOIN p.category c
        WHERE p.id = :id'
            )->setParameter('id', $productId);

        try {
            return $query->getSingleResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            return null;
        }
    }
}
