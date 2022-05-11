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

namespace Customize\Controller;

use Customize\Entity\ProductSeminar;
use Customize\Form\Type\AddCartTypeBySerminar;
use Customize\Form\Type\SearchSeminarType;
use Customize\Repository\ProductSeminarRepository;
use Customize\Repository\SeminarRepository;
use Eccube\Controller\AbstractController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Product;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
//use Eccube\Form\Type\AddCartType;
use Customize\Form\Type\AddCartType;
use Eccube\Form\Type\Master\ProductListMaxType;
use Eccube\Form\Type\Master\ProductListOrderByType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CustomerFavoriteProductRepository;
use Eccube\Repository\Master\ProductListMaxRepository;
use Eccube\Repository\OrderItemRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Eccube\Service\PurchaseFlow\PurchaseFlow;
use Eccube\Util\FormUtil;
use Knp\Component\Pager\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class SeminarController extends AbstractController
{
    /**
     * @var PurchaseFlow
     */
    protected $purchaseFlow;

    /**
     * @var CustomerFavoriteProductRepository
     */
    protected $customerFavoriteProductRepository;

    /**
     * @var CartService
     */
    protected $cartService;

    /**
     * @var SeminarRepository
     */
    protected $seminarRepository;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var AuthenticationUtils
     */
    protected $helper;

    /**
     * @var ProductListMaxRepository
     */
    protected $productListMaxRepository;

    /**
     * @var
     */
    protected $productSeminarRepository;

    /**
     * @var OrderItemRepository
     */
    private $OrderItemRepository;

    /**
     * @var MailService
     */
    protected $mailService;


    private $title = '';

    /**
     * ProductController constructor.
     */
    public function __construct(
        PurchaseFlow $cartPurchaseFlow,
        CustomerFavoriteProductRepository $customerFavoriteProductRepository,
        CartService $cartService,
        SeminarRepository $seminarRepository,
        BaseInfoRepository $baseInfoRepository,
        AuthenticationUtils $helper,
        ProductSeminarRepository $productSeminarRepository,
        OrderItemRepository $OrderItemRepository,
        MailService $mailService,
        ProductListMaxRepository $productListMaxRepository
    ) {
        $this->purchaseFlow = $cartPurchaseFlow;
        $this->customerFavoriteProductRepository = $customerFavoriteProductRepository;
        $this->cartService = $cartService;
        $this->seminarRepository = $seminarRepository;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->helper = $helper;
        $this->productSeminarRepository = $productSeminarRepository;
        $this->OrderItemRepository = $OrderItemRepository;
        $this->mailService = $mailService;
        $this->productListMaxRepository = $productListMaxRepository;
    }

    /**
     * 商品一覧画面.
     *
     * @Route("/seminars/list", name="seminar_list")
     * @Template("Seminar/list.twig")
     */
    public function index(Request $request, Paginator $paginator): array
    {
        // handleRequestは空のqueryの場合は無視するため
        $page_no = $request->query->get('pageno', 1);
        $highlight = $request->query->get('highlight', '');
        $mode = $request->query->get('mode', '');
        if ($request->getMethod() === 'GET') {
            $request->query->set('pageno', $request->query->get('pageno', 1));
            $request->query->set('store_id', $request->query->get('store_id', ''));
            $request->query->set('highlight', $request->query->get('highlight', ''));
            $request->query->set('mode', $request->query->get('mode', ''));
        }
        $builder = $this->formFactory
            ->createBuilder(SearchSeminarType::class);
        $builder->setMethod('GET');
        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_INDEX_INITIALIZE, $event);

        $searchForm = $builder->getForm();

        $page_count = 10;
        $page_count_param = (int) $request->get('page_count');
        $pageMaxis = $this->productListMaxRepository->findAll();

        if ($page_count_param) {
            foreach ($pageMaxis as $pageMax) {
                if ($page_count_param == $pageMax->getName()) {
                    $page_count = $pageMax->getName();
                    $this->session->set('eccube.admin.product.search.page_count', $page_count);
                    break;
                }
            }
        }

        $searchData['highlight'] = (int) $highlight;
        $searchData['mode'] = $mode;
        $searchForm->handleRequest($request);
        $searchData = $searchForm->getData();

        $this->session->set('eccube.admin.product.search.start_time', $searchData['start_time']);

        $qb = $this->seminarRepository->getQueryBuilderBySearchData($searchData);

        $event = new EventArgs(
            [
                'qb' => $qb,
                'searchData' => $searchData,
            ],
            $request
        );

        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_INDEX_SEARCH, $event);

        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $page_count
        );

        return [
            'searchForm' => $searchForm->createView(),
            'searchData' => $searchData,
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $page_count,
            'has_errors' => false,
        ];
    }

    /**
     * 商品詳細画面.
     *
     * @Route("/seminar/detail/{id}", name="seminar_detail", methods={"GET"}, requirements={"id" = "\d+"})
     * @Template("Seminar/detail.twig")
     * @ParamConverter("Seminar", options={"repository_method" = "findWithSortedClassCategories"})
     *
     * @return array
     */
    public function detail(Request $request, Product $Product)
    {
        if (!$this->checkVisibility($Product)) {
            throw new NotFoundHttpException();
        }

        $builder = $this->formFactory->createNamedBuilder(
            '',
            AddCartType::class,
            null,
            [
                'product' => $Product,
                'id_add_product_id' => false,
            ]
        );

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Product' => $Product,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_DETAIL_INITIALIZE, $event);

        return [
            'title' => $this->title,
            'subtitle' => $Product->getName(),
            'form' => $builder->getForm()->createView(),
            'Product' => $Product,
        ];
    }

    /**
     * カートに追加.
     *
     * @Route("/products/add_cart/{id}", name="product_add_cart", methods={"POST"}, requirements={"id" = "\d+"})
     */
//    public function addCart(Request $request, Product $Product)
//    {
//        // エラーメッセージの配列
//        $errorMessages = [];
//        if (!$this->checkVisibility($Product)) {
//            throw new NotFoundHttpException();
//        }
//
//        $builder = $this->formFactory->createNamedBuilder(
//            '',
//            AddCartType::class,
//            null,
//            [
//                'product' => $Product,
//                'id_add_product_id' => false,
//            ]
//        );
//
//        $event = new EventArgs(
//            [
//                'builder' => $builder,
//                'Product' => $Product,
//            ],
//            $request
//        );
//        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_CART_ADD_INITIALIZE, $event);
//
//        /* @var $form \Symfony\Component\Form\FormInterface */
//        $form = $builder->getForm();
//        $form->handleRequest($request);
//
//        if (!$form->isValid()) {
//            throw new NotFoundHttpException();
//        }
//
//        $addCartData = $form->getData();
//
//        log_info(
//            'カート追加処理開始',
//            [
//                'product_id' => $Product->getId(),
//                'product_class_id' => $addCartData['product_class_id'],
//                'quantity' => $addCartData['quantity'],
//            ]
//        );
//
//        // カートへ追加
//        $this->cartService->addProduct($addCartData['product_class_id'], $addCartData['quantity']);
//
//        // 明細の正規化
//        $Carts = $this->cartService->getCarts();
//        foreach ($Carts as $Cart) {
//            $result = $this->purchaseFlow->validate($Cart, new PurchaseContext($Cart, $this->getUser()));
//            // 復旧不可のエラーが発生した場合は追加した明細を削除.
//            if ($result->hasError()) {
//                $this->cartService->removeProduct($addCartData['product_class_id']);
//                foreach ($result->getErrors() as $error) {
//                    $errorMessages[] = $error->getMessage();
//                }
//            }
//            foreach ($result->getWarning() as $warning) {
//                $errorMessages[] = $warning->getMessage();
//            }
//        }
//
//        $this->cartService->save();
//
//        log_info(
//            'カート追加処理完了',
//            [
//                'product_id' => $Product->getId(),
//                'product_class_id' => $addCartData['product_class_id'],
//                'quantity' => $addCartData['quantity'],
//            ]
//        );
//
//        $event = new EventArgs(
//            [
//                'form' => $form,
//                'Product' => $Product,
//            ],
//            $request
//        );
//        $this->eventDispatcher->dispatch(EccubeEvents::FRONT_PRODUCT_CART_ADD_COMPLETE, $event);
//
//        if ($event->getResponse() !== null) {
//            return $event->getResponse();
//        }
//
//        if ($request->isXmlHttpRequest()) {
//            // ajaxでのリクエストの場合は結果をjson形式で返す。
//
//            // 初期化
//            $done = null;
//            $messages = [];
//
//            if (empty($errorMessages)) {
//                // エラーが発生していない場合
//                $done = true;
//                array_push($messages, trans('front.product.add_cart_complete'));
//            } else {
//                // エラーが発生している場合
//                $done = false;
//                $messages = $errorMessages;
//            }
//
//            return $this->json(['done' => $done, 'messages' => $messages]);
//        } else {
//            // ajax以外でのリクエストの場合はカート画面へリダイレクト
//            foreach ($errorMessages as $errorMessage) {
//                $this->addRequestError($errorMessage);
//            }
//
//            return $this->redirectToRoute('cart');
//        }
//    }

    /**
     * ページタイトルの設定
     *
     * @param  array|null $searchData
     *
     * @return str
     */
    protected function getPageTitle($searchData)
    {
        if (isset($searchData['name']) && !empty($searchData['name'])) {
            return trans('front.product.search_result');
        } elseif (isset($searchData['category_id']) && $searchData['category_id']) {
            return $searchData['category_id']->getName();
        } else {
            return trans('front.product.all_products');
        }
    }

    /**
     * 閲覧可能な商品かどうかを判定
     *
     * @return boolean 閲覧可能な場合はtrue
     */
    protected function checkVisibility(Product $Product)
    {
        $is_admin = $this->session->has('_security_admin');

        // 管理ユーザの場合はステータスやオプションにかかわらず閲覧可能.
        if (!$is_admin) {
            // 在庫なし商品の非表示オプションが有効な場合.
            // if ($this->BaseInfo->isOptionNostockHidden()) {
            //     if (!$Product->getStockFind()) {
            //         return false;
            //     }
            // }
            // 公開ステータスでない商品は表示しない.
            if ($Product->getStatus()->getId() !== ProductStatus::DISPLAY_SHOW) {
                return false;
            }
        }

        return true;
    }

    /**
     * ==============================================================================
     *                      SEND MAIL CONTACT.
     * ==============================================================================
     */
    /**
     * @Route("/seminar/contact", name="seminar_contact", methods={"POST"})
     */
    public function contact(Request $request): JsonResponse
    {
        if ('POST' === $request->getMethod()) {
            $dataRequest = $request->request->all();
            try {
                $this->mailService->sendContactSeminarMail($dataRequest);
                $response['code'] = 204;
                $response['message'] = 'メールが送信されました';
            } catch (LoaderError | RuntimeError | SyntaxError $e) {
                $response['code'] = 400;
                $response['message'] = $e->getMessage();
            }
        }

        return $this->json($response);
    }
}
