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

namespace Customize\Controller\Admin\System;

use Customize\Entity\StoreDelivery;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
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
use Customize\Repository\StoreRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Customize\Repository\StoreMemberRepository;

class StoreDeliveryController extends AbstractController
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
     * @var StoreRepository
     */
    protected $storeRepository;

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
        StoreRepository $storeRepository,
        TokenStorageInterface $tokenStorage,
        StoreMemberRepository $storeMemberRepository
    )
    {
        $this->paymentOptionRepository = $paymentOptionRepository;
        $this->deliveryFeeRepository = $deliveryFeeRepository;
        $this->prefRepository = $prefRepository;
        $this->deliveryRepository = $deliveryRepository;
        $this->deliveryTimeRepository = $deliveryTimeRepository;
        $this->saleTypeRepository = $saleTypeRepository;
        $this->storeRepository = $storeRepository;
        $this->tokenStorage = $tokenStorage;
        $this->storeMemberRepository = $storeMemberRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/setting/shop/delivery/new", name="admin_setting_shop_delivery_new")
     * @Route("/%eccube_admin_route%/setting/shop/delivery/new/{IdStore}", name="admin_setting_system_store_delivery_new")
     * @Template("@admin/Shop/delivery.twig")
     */
    public function createStoreDelivery(Request $request, $IdStore = null)
    {
        $SaleType = $this->saleTypeRepository->findOneBy([], ['sort_no' => 'ASC']);
        $Delivery = $this->deliveryRepository->findOneBy([], ['sort_no' => 'DESC']);

        $sortNo = 1;
        if ($Delivery) {
            $sortNo = $Delivery->getSortNo() + 1;
        }

        $Delivery = new Delivery();
        $Delivery
            ->setSortNo($sortNo)
            ->setVisible(true)
            ->setSaleType($SaleType);

        $originalDeliveryTimes = new ArrayCollection();

        foreach ($Delivery->getDeliveryTimes() as $deliveryTime) {
            $originalDeliveryTimes->add($deliveryTime);
        }

        // FormType: DeliveryFeeの生成
        $Prefs = $this->prefRepository
            ->findAll();

        foreach ($Prefs as $Pref) {
            $DeliveryFee = $this->deliveryFeeRepository
                ->findOneBy(
                    [
                        'Delivery' => $Delivery,
                        'Pref' => $Pref,
                    ]
                );
            if (!$DeliveryFee) {
                $DeliveryFee = new DeliveryFee();
                $DeliveryFee
                    ->setPref($Pref)
                    ->setDelivery($Delivery);
            }
            if (!$DeliveryFee->getFee()) {
                $Delivery->addDeliveryFee($DeliveryFee);
            }
        }

        $DeliveryFees = $Delivery->getDeliveryFees();
        $DeliveryFeesIndex = [];
        foreach ($DeliveryFees as $DeliveryFee) {
            $Delivery->removeDeliveryFee($DeliveryFee);
            $DeliveryFeesIndex[$DeliveryFee->getPref()->getId()] = $DeliveryFee;
        }
        ksort($DeliveryFeesIndex);
        foreach ($DeliveryFeesIndex as $timeId => $DeliveryFee) {
            $Delivery->addDeliveryFee($DeliveryFee);
        }

        $builder = $this->formFactory
            ->createBuilder(DeliveryType::class, $Delivery);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Delivery' => $Delivery,
                'Prefs' => $Prefs,
                'DeliveryFees' => $DeliveryFees,
            ],
            $request
        );

        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_SETTING_SHOP_DELIVERY_EDIT_INITIALIZE, $event);

        $form = $builder->getForm();

        // 支払方法をセット
        $Payments = [];
        foreach ($Delivery->getPaymentOptions() as $PaymentOption) {
            $Payments[] = $PaymentOption->getPayment();
        }

        $form['delivery_times']->setData($Delivery->getDeliveryTimes());
        $form['payments']->setData($Payments);


        // 登録ボタン押下
        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $DeliveryData = $form->getData();

                // 配送時間の登録
                /** @var DeliveryTime $DeliveryTime */
                foreach ($originalDeliveryTimes as $DeliveryTime) {
                    if (false === $Delivery->getDeliveryTimes()->contains($DeliveryTime)) {
                        $this->entityManager->remove($DeliveryTime);
                    }
                }
                foreach ($DeliveryData['DeliveryTimes'] as $DeliveryTime) {
                    $DeliveryTime->setDelivery($Delivery);
                }

                // お支払いの登録
                $PaymentOptions = $this->paymentOptionRepository
                    ->findBy(['delivery_id' => $Delivery->getId()]);
                // 消す
                foreach ($PaymentOptions as $PaymentOption) {
                    $DeliveryData->removePaymentOption($PaymentOption);
                    $this->entityManager->remove($PaymentOption);
                }
                $this->entityManager->persist($DeliveryData);
                $this->entityManager->flush();


                $LoginMember = clone $this->tokenStorage->getToken()->getUser();
                $data = $this->storeMemberRepository->findBy(array('Member' => $LoginMember));

                if($LoginMember->getAuthority()->getId() !== 0){
                    $IdStore = $data[0]->getStore()->getId();
                    $Store = $this->storeRepository->find($IdStore);
                    $storeDelivery = new StoreDelivery();
                    $storeDelivery->setDelivery($Delivery);
                    $storeDelivery->setStore($Store);
                    $this->entityManager->persist($storeDelivery);
                    $this->entityManager->flush();
                }

                // いれる
                $PaymentsData = $form->get('payments')->getData();
                foreach ($PaymentsData as $PaymentData) {
                    $PaymentOption = new PaymentOption();
                    $PaymentOption
                        ->setPaymentId($PaymentData->getId())
                        ->setPayment($PaymentData)
                        ->setDeliveryId($DeliveryData->getId())
                        ->setDelivery($DeliveryData);
                    $DeliveryData->addPaymentOption($PaymentOption);
                    $this->entityManager->persist($DeliveryData);
                }

                $this->entityManager->persist($DeliveryData);

                $this->entityManager->flush();

                $event = new EventArgs(
                    [
                        'form' => $form,
                        'Delivery' => $Delivery,
                        'Prefs' => $Prefs,
                        'DeliveryFees' => $DeliveryFees,
                    ],
                    $request
                );
                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_SETTING_SHOP_DELIVERY_EDIT_COMPLETE, $event);


                $this->addSuccess('admin.common.save_complete', 'admin');

                return $this->redirectToRoute('admin_setting_shop_delivery_edit', ['id' => $Delivery->getId()]);
            }
        }

        return [
            'form' => $form->createView(),
            'delivery_id' => $Delivery->getId(),
            'id_store' => $IdStore

        ];
    }
}
