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

namespace Customize\Controller;;

use Customize\Repository\ProductSeminarRepository;
use Eccube\Controller\AbstractController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\ProductClass;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CartItemRepository;
use Eccube\Repository\ProductClassRepository;
use Customize\Service\CartService;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Service\PurchaseFlow\PurchaseFlowResult;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
{
    /**
     * @var ProductClassRepository
     */
    protected $productClassRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var BaseInfo
     */
    protected $baseInfo;

    /**
     * @var ProductSeminarRepository
     */
    protected $productSeminarRepository;

    /**
     * @var CartItemRepository
     */
    protected $cartItemRepository;

    /**
     * CartController constructor.
     */
    public function __construct(
        ProductClassRepository $productClassRepository,
        CartService $cartService,
        PurchaseFlow $cartPurchaseFlow,
        BaseInfoRepository $baseInfoRepository,
        ProductSeminarRepository $productSeminarRepository,
        CartItemRepository $cartItemRepository
    ) {
        $this->productClassRepository = $productClassRepository;
        $this->cartService = $cartService;
        $this->purchaseFlow = $cartPurchaseFlow;
        $this->baseInfo = $baseInfoRepository->get();
        $this->productSeminarRepository = $productSeminarRepository;
        $this->cartItemRepository = $cartItemRepository;
    }

    /**
     * ???????????????.
     *
     * @Route("/cart", name="cart")
     * @Template("Cart/index.twig")
     */
    public function index(Request $request)
    {
        // ???????????????????????????????????????????????????
        $Carts = $this->cartService->getCarts();
        $this->execPurchaseFlow($Carts);

    // TODO itemHolder??????????????????????????????
        $least = [];
        $quantity = [];
        $isDeliveryFree = [];
        $totalPrice = 0;
        $totalQuantity = 0;
        $productSeminar = [];

        foreach ($Carts as $Cart) {
            $quantity[$Cart->getCartKey()] = 0;
            $isDeliveryFree[$Cart->getCartKey()] = false;

            if ($this->baseInfo->getDeliveryFreeQuantity()) {
                if ($this->baseInfo->getDeliveryFreeQuantity() > $Cart->getQuantity()) {
                    $quantity[$Cart->getCartKey()] = $this->baseInfo->getDeliveryFreeQuantity() - $Cart->getQuantity();
                } else {
                    $isDeliveryFree[$Cart->getCartKey()] = true;
                }
            }

            if ($this->baseInfo->getDeliveryFreeAmount()) {
                if (!$isDeliveryFree[$Cart->getCartKey()] && $this->baseInfo->getDeliveryFreeAmount() <= $Cart->getTotalPrice()) {
                    $isDeliveryFree[$Cart->getCartKey()] = true;
                } else {
                    $least[$Cart->getCartKey()] = $this->baseInfo->getDeliveryFreeAmount() - $Cart->getTotalPrice();
                }
            }

            $totalPrice += $Cart->getTotalPrice();
            $totalQuantity += $Cart->getQuantity();

            foreach ($Cart->getCartItems() as $key=>$val) {
                $cartItem = $this->cartItemRepository->findBy(['id' => $val['id']]);
                $productSeminar[$Cart->getCartKey()][$key] = $this->productSeminarRepository->findBy(['id' => $cartItem[0]['schedule_id']]);
            }
        }


        // ???????????????????????????????????????????????????????????????
        $request->getSession()->remove(OrderHelper::SESSION_CART_DIVIDE_FLAG);

        $cartData = $this->cartService->getCarts(false);
        
        $data = [
            'totalPrice' => $totalPrice,
            'totalQuantity' => $totalQuantity,
            // ??????????????????????????????????????????
            'Carts' => $cartData,
            'least' => $least,
            'quantity' => $quantity,
            'is_delivery_free' => $isDeliveryFree,
            'productSeminar' => $productSeminar
        ];
        return $data;
    }

    /**
     * @param $Carts
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function execPurchaseFlow($Carts)
    {
        /** @var PurchaseFlowResult[] $flowResults */
        $flowResults = array_map(function ($Cart) {
            $purchaseContext = new PurchaseContext($Cart, $this->getUser());

            return $this->purchaseFlow->validate($Cart, $purchaseContext);
        }, $Carts);

        // ????????????????????????????????????????????????????????????????????????????????????
        $hasError = false;
        foreach ($flowResults as $result) {
            if ($result->hasError()) {
                $hasError = true;
                foreach ($result->getErrors() as $error) {
                    $this->addRequestError($error->getMessage());
                }
            }
        }
        if ($hasError) {
            $this->cartService->clear();

            return $this->redirectToRoute('cart');
        }

        $this->cartService->save();

        foreach ($flowResults as $index => $result) {
            foreach ($result->getWarning() as $warning) {
                if ($Carts[$index]->getItems()->count() > 0) {
                    $cart_key = $Carts[$index]->getCartKey();
                    $this->addRequestError($warning->getMessage(), "front.cart.${cart_key}");
                } else {
                    // ???????????????????????????????????????????????????????????????????????????
                    $this->addRequestError($warning->getMessage());
                }
            }
        }
    }

    /**
     * ????????????????????????/??????/???????????????.
     *
     * - ??????
     *      - ??????????????????1?????????
     * - ??????
     *      - ??????????????????1?????????
     *      - ?????????0??????????????????????????????????????????
     * - ??????
     *      - ?????????????????????
     *
     * @Route(
     *     path="/cart/{operation}/{productClassId}",
     *     name="cart_handle_item",
     *     methods={"PUT"},
     *     requirements={
     *          "operation": "up|down|remove",
     *          "productClassId": "\d+"
     *     }
     * )
     */
    public function handleCartItem($operation, $productClassId)
    {
        log_info('???????????????????????????', ['operation' => $operation, 'product_class_id' => $productClassId]);

        $this->isTokenValid();

        /** @var ProductClass $ProductClass */
        $ProductClass = $this->productClassRepository->find($productClassId);

        if (is_null($ProductClass)) {
            log_info('???????????????????????????????????????????????????redirect', ['operation' => $operation, 'product_class_id' => $productClassId]);

            return $this->redirectToRoute('cart');
        }

        // ????????????????????????
        switch ($operation) {
            case 'up':
                $this->cartService->addProduct($ProductClass, 1);
                break;
            case 'down':
                $this->cartService->addProduct($ProductClass, -1);
                break;
            case 'remove':
                $this->cartService->removeProduct($ProductClass);
                break;
        }

        // ???????????????????????????????????????????????????
        $Carts = $this->cartService->getCarts();
        $this->execPurchaseFlow($Carts);

        log_info('???????????????????????????', ['operation' => $operation, 'product_class_id' => $productClassId]);

        return $this->redirectToRoute('cart');
    }

    /**
     * ???????????????????????????????????????????????????????????????????????????.
     *
     * @Route("/cart/buystep/{cart_key}", name="cart_buystep", requirements={"cart_key" = "[a-zA-Z0-9]+[_][\x20-\x7E]+"})
     */
    public function buystep(Request $request, $cart_key)
    {
        $Carts = $this->cartService->getCart();
        if (!is_object($Carts)) {
            return $this->redirectToRoute('cart');
        }
        // FRONT_CART_BUYSTEP_INITIALIZE
        $event = new EventArgs(
            [],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_CART_BUYSTEP_INITIALIZE, $event);

        $this->cartService->setPrimary($cart_key);
        $this->cartService->save();

        // FRONT_CART_BUYSTEP_COMPLETE
        $event = new EventArgs(
            [],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_CART_BUYSTEP_COMPLETE, $event);

        if ($event->hasResponse()) {
            return $event->getResponse();
        }

        return $this->redirectToRoute('shopping');
    }
}
