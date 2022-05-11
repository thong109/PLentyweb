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

namespace Customize\Controller\Admin\Seminar;

use Customize\Entity\ProductSeminar;
use Customize\Form\Type\Admin\SearchSeminarType;
use Customize\Repository\ProductSeminarRepository;
use Customize\Repository\SeminarRepository;
use Customize\Service\ZoomService;
use DateTime;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\NoResultException;
use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Category;
use Eccube\Entity\ExportCsvRow;
use Eccube\Entity\Master\CsvType;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Product;
use Eccube\Entity\ProductCategory;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ProductImage;
use Eccube\Entity\ProductStock;
use Eccube\Entity\ProductTag;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Admin\SeminarType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CategoryRepository;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Repository\Master\ProductStatusRepository;
use Eccube\Repository\Master\SaleTypeRepository;
use Eccube\Repository\OrderItemRepository;
use Eccube\Repository\ProductClassRepository;
use Eccube\Repository\ProductImageRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Repository\TagRepository;
use Eccube\Repository\TaxRuleRepository;
use Eccube\Service\CsvExportService;
use Eccube\Service\MailService;
use Eccube\Util\CacheUtil;
use Eccube\Util\FormUtil;
use Exception;
use Knp\Component\Pager\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Customize\Repository\StoreMemberRepository;

class SeminarController extends AbstractController
{
    /**
     * @var
     */
    protected $productRepository;

    /**
     * @var
     */
    protected $productSeminarRepository;

    /**
     * @var SeminarRepository
     */
    protected $seminarRepository;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;
    /**
     * @var ProductClassRepository
     */
    private $productClassRepository;
    /**
     * @var ProductImageRepository
     */
    private $productImageRepository;
    /**
     * @var BaseInfo
     */
    private $BaseInfo;
    /**
     * @var ProductStatusRepository
     */
    private $productStatusRepository;
    /**
     * @var CategoryRepository
     */
    private $categoryRepository;
    /**
     * @var TagRepository
     */
    private $tagRepository;
    /**
     * @var TaxRuleRepository
     */
    private $taxRuleRepository;
    /**
     * @var SaleTypeRepository
     */
    private $saleTypeRepository;

    /**
     * @var CsvExportService
     */
    private $csvExportService;

    /**
     * @var OrderItemRepository
     */
    private $OrderItemRepository;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var StoreMemberRepository
     */
    protected $storeMemberRepository;
    /**
     * SeminarController constructor.
     *
     * @throws Exception
     */
    public function __construct(
        ProductClassRepository $productClassRepository,
        ProductImageRepository $productImageRepository,
        SaleTypeRepository $saleTypeRepository,
        TaxRuleRepository $taxRuleRepository,
        CategoryRepository $categoryRepository,
        SeminarRepository $seminarRepository,
        BaseInfoRepository $baseInfoRepository,
        ProductStatusRepository $productStatusRepository,
        ProductRepository $productRepository,
        TagRepository $tagRepository,
        productSeminarRepository $productSeminarRepository,
        PageMaxRepository $pageMaxRepository,
        CsvExportService $csvExportService,
        OrderItemRepository $OrderItemRepository,
        MailService $mailService,
        TokenStorageInterface $tokenStorage,
        StoreMemberRepository $storeMemberRepository
    ) {
        $this->productClassRepository = $productClassRepository;
        $this->productImageRepository = $productImageRepository;
        $this->saleTypeRepository = $saleTypeRepository;
        $this->taxRuleRepository = $taxRuleRepository;
        $this->categoryRepository = $categoryRepository;
        $this->seminarRepository = $seminarRepository;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->productStatusRepository = $productStatusRepository;
        $this->productRepository = $productRepository;
        $this->tagRepository = $tagRepository;
        $this->productSeminarRepository = $productSeminarRepository;
        $this->pageMaxRepository = $pageMaxRepository;
        $this->csvExportService = $csvExportService;
        $this->OrderItemRepository = $OrderItemRepository;
        $this->mailService = $mailService;
        $this->storeMemberRepository = $storeMemberRepository;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @Route("/%eccube_admin_route%/seminar", name="admin_seminar")
     * @Route("/%eccube_admin_route%/seminar/page/{page_no}", requirements={"page_no" = "\d+"}, name="admin_seminar_page")
     * @Template("@admin/Seminar/index.twig")
     *
     * @param null $page_no
     */
    public function index(Request $request, $page_no = null, Paginator $paginator): array
    {
        $builder = $this->formFactory
            ->createBuilder(SearchSeminarType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_INDEX_INITIALIZE, $event);

        $searchForm = $builder->getForm();

        /**
         * ページの表示件数は, 以下の順に優先される.
         * - リクエストパラメータ
         * - セッション
         * - デフォルト値
         * また, セッションに保存する際は mtb_page_maxと照合し, 一致した場合のみ保存する.
         **/
        $page_count = $this->session->get('eccube.admin.product.search.page_count',
            $this->eccubeConfig->get('eccube_default_page_count'));

        $page_count_param = (int) $request->get('page_count');
        $pageMaxis = $this->pageMaxRepository->findAll();

        if ($page_count_param) {
            foreach ($pageMaxis as $pageMax) {
                if ($page_count_param == $pageMax->getName()) {
                    $page_count = $pageMax->getName();
                    $this->session->set('eccube.admin.product.search.page_count', $page_count);
                    break;
                }
            }
        }

        if ('POST' === $request->getMethod()) {
            $searchForm->handleRequest($request);

            if ($searchForm->isValid()) {
                /**
                 * 検索が実行された場合は, セッションに検索条件を保存する.
                 * ページ番号は最初のページ番号に初期化する.
                 */
                $page_no = 1;
                $searchData = $searchForm->getData();

                // 検索条件, ページ番号をセッションに保持.
                $this->session->set('eccube.admin.product.search', FormUtil::getViewData($searchForm));
                $this->session->set('eccube.admin.product.search.page_no', $page_no);
            } else {
                // 検索エラーの際は, 詳細検索枠を開いてエラー表示する.
                return [
                    'searchForm' => $searchForm->createView(),
                    'pagination' => [],
                    'pageMaxis' => $pageMaxis,
                    'page_no' => $page_no,
                    'page_count' => $page_count,
                    'has_errors' => true,
                ];
            }
        } else {
            if (null !== $page_no || $request->get('resume')) {
                /*
                 * ページ送りの場合または、他画面から戻ってきた場合は, セッションから検索条件を復旧する.
                 */
                if ($page_no) {
                    // ページ送りで遷移した場合.
                    $this->session->set('eccube.admin.product.search.page_no', (int) $page_no);
                } else {
                    // 他画面から遷移した場合.
                    $page_no = $this->session->get('eccube.admin.product.search.page_no', 1);
                }
                $viewData = $this->session->get('eccube.admin.product.search', []);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);
            } else {
                /**
                 * 初期表示の場合.
                 */
                $page_no = 1;
                // submit default value
                $viewData = FormUtil::getViewData($searchForm);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

                // セッション中の検索条件, ページ番号を初期化.
                $this->session->set('eccube.admin.product.search', $viewData);
                $this->session->set('eccube.admin.product.search.page_no', $page_no);
            }
        }
        $LoginMember = clone $this->tokenStorage->getToken()->getUser();
        $data = $this->storeMemberRepository->findBy(array('Member' => $LoginMember));
        if($data){
            $Store = $data[0]->getStore()->getId();
            $searchData['store_id'] = $Store;
        }

        $qb = $this->seminarRepository->getQueryBuilderBySearchDataForAdmin($searchData);

        $event = new EventArgs(
            [
                'qb' => $qb,
                'searchData' => $searchData,
            ],
            $request
        );

        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_INDEX_SEARCH, $event);

        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $page_count
        );

        return [
            'searchForm' => $searchForm->createView(),
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $page_count,
            'has_errors' => false,
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/seminar/new", name="admin_seminar_new")
     * @Route("/%eccube_admin_route%/seminar/{id}/edit", requirements={"id" = "\d+"}, name="admin_seminar_edit")
     * @Template("@admin/Seminar/seminar.twig")
     *
     * @param null $id
     *
     * @return array
     *
     * @throws NoResultException
     * @throws Exception
     */
    public function edit(Request $request, $id = null, RouterInterface $router, CacheUtil $cacheUtil)
    {
        $has_class = false;

        if (is_null($id)) {
            $Product = new Product();
            $ProductClass = new ProductClass();
            $ProductStatus = $this->productStatusRepository->find(ProductStatus::DISPLAY_HIDE);
            $LoginMember = clone $this->tokenStorage->getToken()->getUser();
            $data = $this->storeMemberRepository->findBy(array('Member' => $LoginMember));
            $Product
                ->addProductClass($ProductClass)
                ->setStatus($ProductStatus);
            if($data){
                $Store = $data[0]->getStore();
                $Product->setStore($Store);
            }

            $ProductClass
                ->setVisible(true)
                ->setStockUnlimited(true)
                ->setProduct($Product);
            $ProductStock = new ProductStock();
            $ProductClass->setProductStock($ProductStock);
            $ProductStock->setProductClass($ProductClass);
        } else {
            $Product = $this->productRepository->find($id);
            if (!$Product) {
                throw new NotFoundHttpException();
            }
            // 規格無しの商品の場合は、デフォルト規格を表示用に取得する
            $has_class = $Product->hasProductClass();
            if (!$has_class) {
                $ProductClasses = $Product->getProductClasses();
                foreach ($ProductClasses as $pc) {
                    if (!is_null($pc->getClassCategory1())) {
                        continue;
                    }
                    if ($pc->isVisible()) {
                        $ProductClass = $pc;
                        break;
                    }
                }
                if ($this->BaseInfo->isOptionProductTaxRule() && $ProductClass->getTaxRule()) {
                    $ProductClass->setTaxRate($ProductClass->getTaxRule()->getTaxRate());
                }
                $ProductStock = $ProductClass->getProductStock();
            }
        }

        $builder = $this->formFactory
            ->createBuilder(SeminarType::class, $Product);

        // 規格あり商品の場合、規格関連情報をFormから除外
        if ($has_class) {
            $builder->remove('class');
        }

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Product' => $Product,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_EDIT_INITIALIZE, $event);

        $form = $builder->getForm();

        if (!$has_class) {
            $form['class']->setData($ProductClass);
        }

        // ファイルの登録
        $images = [];
        $ProductImages = $Product->getProductImage();
        foreach ($ProductImages as $ProductImage) {
            $images[] = $ProductImage->getFileName();
        }
        $form['images']->setData($images);

        $categories = [];
        $ProductCategories = $Product->getProductCategories();
        foreach ($ProductCategories as $ProductCategory) {
            /* @var $ProductCategory ProductCategory */
            $categories[] = $ProductCategory->getCategory();
        }
        $form['Category']->setData($categories);

        $Tags = $Product->getTags();
        $form['Tag']->setData($Tags);

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            $form->getErrors(true);
            if ($form->isSubmitted() && $form->isValid()) {
                log_info('商品登録開始', [$id]);
                $Product = $form->getData();

                if (!$has_class) {
                    $ProductClass = $form['class']->getData();

                    // 個別消費税
                    if ($this->BaseInfo->isOptionProductTaxRule()) {
                        if ($ProductClass->getTaxRate() !== null) {
                            if ($ProductClass->getTaxRule()) {
                                $ProductClass->getTaxRule()->setTaxRate($ProductClass->getTaxRate());
                            } else {
                                $taxrule = $this->taxRuleRepository->newTaxRule();
                                $taxrule->setTaxRate($ProductClass->getTaxRate());
                                $taxrule->setApplyDate(new \DateTime());
                                $taxrule->setProduct($Product);
                                $taxrule->setProductClass($ProductClass);
                                $ProductClass->setTaxRule($taxrule);
                            }

                            $ProductClass->getTaxRule()->setTaxRate($ProductClass->getTaxRate());
                        } else {
                            if ($ProductClass->getTaxRule()) {
                                $this->taxRuleRepository->delete($ProductClass->getTaxRule());
                                $ProductClass->setTaxRule(null);
                            }
                        }
                    }
                    $this->entityManager->persist($ProductClass);

                    // 在庫情報を作成
                    if (!$ProductClass->isStockUnlimited()) {
                        $ProductStock->setStock($ProductClass->getStock());
                    } else {
                        // 在庫無制限時はnullを設定
                        $ProductStock->setStock(null);
                    }
                    $this->entityManager->persist($ProductStock);
                }

                // カテゴリの登録
                // 一度クリア
                /* @var $Product Product */
                foreach ($Product->getProductCategories() as $ProductCategory) {
                    $Product->removeProductCategory($ProductCategory);
                    $this->entityManager->remove($ProductCategory);
                }
                $this->entityManager->persist($Product);
                $this->entityManager->flush();

                $count = 1;
                $Categories = $form->get('Category')->getData();
                $categoriesIdList = [];
                foreach ($Categories as $Category) {
                    foreach ($Category->getPath() as $ParentCategory) {
                        if (!isset($categoriesIdList[$ParentCategory->getId()])) {
                            $ProductCategory = $this->createProductCategory($Product, $ParentCategory, $count);
                            $this->entityManager->persist($ProductCategory);
                            $count++;
                            /* @var $Product Product */
                            $Product->addProductCategory($ProductCategory);
                            $categoriesIdList[$ParentCategory->getId()] = true;
                        }
                    }
                    if (!isset($categoriesIdList[$Category->getId()])) {
                        $ProductCategory = $this->createProductCategory($Product, $Category, $count);
                        $this->entityManager->persist($ProductCategory);
                        $count++;
                        /* @var $Product Product */
                        $Product->addProductCategory($ProductCategory);
                        $categoriesIdList[$ParentCategory->getId()] = true;
                    }
                }

                // 画像の登録
                $add_images = $form->get('add_images')->getData();
                foreach ($add_images as $add_image) {
                    $ProductImage = new \Eccube\Entity\ProductImage();
                    $ProductImage
                        ->setFileName($add_image)
                        ->setProduct($Product)
                        ->setSortNo(1);
                    $Product->addProductImage($ProductImage);
                    $this->entityManager->persist($ProductImage);

                    // 移動
                    $file = new File($this->eccubeConfig['eccube_temp_image_dir'].'/'.$add_image);
                    $file->move($this->eccubeConfig['eccube_save_image_dir']);
                }

                // 画像の削除
                $delete_images = $form->get('delete_images')->getData();
                foreach ($delete_images as $delete_image) {
                    $ProductImage = $this->productImageRepository
                        ->findOneBy(['file_name' => $delete_image]);

                    // 追加してすぐに削除した画像は、Entityに追加されない
                    if ($ProductImage instanceof ProductImage) {
                        $Product->removeProductImage($ProductImage);
                        $this->entityManager->remove($ProductImage);
                    }
                    $this->entityManager->persist($Product);
                    $this->entityManager->flush();

                    if (!$this->productImageRepository->findOneBy(['file_name' => $delete_image])) {
                        // 削除
                        $fs = new Filesystem();
                        $fs->remove($this->eccubeConfig['eccube_save_image_dir'].'/'.$delete_image);
                    }
                }
                $this->entityManager->persist($Product);
                $this->entityManager->flush();

                $sortNos = $request->get('sort_no_images');
                if ($sortNos) {
                    foreach ($sortNos as $sortNo) {
                        list($filename, $sortNo_val) = explode('//', $sortNo);
                        $ProductImage = $this->productImageRepository
                            ->findOneBy([
                                'file_name' => $filename,
                                'Product' => $Product,
                            ]);
                        $ProductImage->setSortNo($sortNo_val);
                        $this->entityManager->persist($ProductImage);
                    }
                }
                $this->entityManager->flush();

                // 商品タグの登録
                // 商品タグを一度クリア
                $ProductTags = $Product->getProductTag();
                foreach ($ProductTags as $ProductTag) {
                    $Product->removeProductTag($ProductTag);
                    $this->entityManager->remove($ProductTag);
                }

                // 商品タグの登録
                $Tags = $form->get('Tag')->getData();
                foreach ($Tags as $Tag) {
                    $ProductTag = new ProductTag();
                    $ProductTag
                        ->setProduct($Product)
                        ->setTag($Tag);
                    $Product->addProductTag($ProductTag);
                    $this->entityManager->persist($ProductTag);
                }

                // mapping product and schedule
                $meeting_id = $request->get('meeting_id');
                if ($meeting_id) {
                    for ($i = 0; $i < count($meeting_id); $i++) {
                        $ProductSeminar = $this->productSeminarRepository->findBy(['zoomId' => $request->get('meeting_id')[$i]]);
                        if (!$ProductSeminar) {
                            $ProductSeminar = new ProductSeminar();
                            $ProductSeminar
                                ->setProduct($Product)
                                ->setZoomPassword($request->get('password')[$i])
                                ->setZoomId($request->get('meeting_id')[$i])
                                ->setStartTime(new \DateTime($request->get('start_time')[$i]))
                                ->setDuration((int) $request->get('duration')[$i])
                                ->setJoinUrl($request->get('meeting_url')[$i]);
                            $this->entityManager->persist($ProductSeminar);
                        }
                    }
                }

                $Product->setUpdateDate(new \DateTime());
                $this->entityManager->flush();

                log_info('商品登録完了', [$id]);

                $event = new EventArgs(
                    [
                        'form' => $form,
                        'Product' => $Product,
                    ],
                    $request
                );
                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_EDIT_COMPLETE, $event);

                $this->addSuccess('admin.common.save_complete', 'admin');

                if ($returnLink = $form->get('return_link')->getData()) {
                    try {
                        // $returnLinkはpathの形式で渡される. pathが存在するかをルータでチェックする.
                        $pattern = '/^'.preg_quote($request->getBasePath(), '/').'/';
                        $returnLink = preg_replace($pattern, '', $returnLink);
                        $result = $router->match($returnLink);
                        // パラメータのみ抽出
                        $params = array_filter($result, function ($key) {
                            return 0 !== \strpos($key, '_');
                        }, ARRAY_FILTER_USE_KEY);

                        // pathからurlを再構築してリダイレクト.
                        return $this->redirectToRoute($result['_route'], $params);
                    } catch (\Exception $e) {
                        // マッチしない場合はログ出力してスキップ.
                        log_warning('URLの形式が不正です。');
                    }
                }

                $cacheUtil->clearDoctrineCache();

                return $this->redirectToRoute('admin_seminar_edit', ['id' => $Product->getId()]);
            }
        }

        // 検索結果の保持
        $builder = $this->formFactory
            ->createBuilder(SearchSeminarType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Product' => $Product,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_EDIT_SEARCH, $event);

        $searchForm = $builder->getForm();

        if ('POST' === $request->getMethod()) {
            $searchForm->handleRequest($request);
        }

        // Get Tags
        $TagsList = $this->tagRepository->getList();

        // ツリー表示のため、ルートからのカテゴリを取得
        $TopCategories = $this->categoryRepository->getList(null);
        $ChoicedCategoryIds = array_map(function ($Category) {
            return $Category->getId();
        }, $form->get('Category')->getData());

        //Get schedule
        $ProductSeminar = $this->productSeminarRepository->findBy(['product' => $Product]);

        return [
            'Product' => $Product,
            'Tags' => $Tags,
            'TagsList' => $TagsList,
            'form' => $form->createView(),
            'searchForm' => $searchForm->createView(),
            'has_class' => $has_class,
            'id' => $id,
            'TopCategories' => $TopCategories,
            'ChoicedCategoryIds' => $ChoicedCategoryIds,
            'Schedule' => $ProductSeminar,
        ];
    }

    /**
     * ProductCategory作成
     *
     * @param Category $Category
     * @param integer $count
     */
    private function createProductCategory(Product $Product, $Category, $count): ProductCategory
    {
        $ProductCategory = new ProductCategory();
        $ProductCategory->setProduct($Product);
        $ProductCategory->setProductId($Product->getId());
        $ProductCategory->setCategory($Category);
        $ProductCategory->setCategoryId($Category->getId());

        return $ProductCategory;
    }

    /**
     * @Route("/seminar/schedule/new", name="admin_seminar_schedule_new", methods={"POST"})
     *
     * @throws Exception
     */
    public function addSchedule(Request $request, CacheUtil $cacheUtil): \Symfony\Component\HttpFoundation\Response
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            if ('POST' === $request->getMethod()) {
                $startTime = new DateTime($request->get('start_time'));
                $data = [
                    'topic' => (string) $request->get('topic'),
                    'type' => 2,
                    'start_time' => (string) $startTime->format('Y-m-d').'T'.$startTime->format('H:i').':00Z',
                    'duration' => (int) $request->get('duration'),
                    'timezone' => 'Asia/Tokyo',
                    'password' => (string) $request->get('password'),
                    'settings' => [
                        'waiting_room' => (bool) $request->get('waiting_room'),
                        'host_video' => (bool) $request->get('host_video'),
                        'participant_video' => (bool) $request->get('participant_video'),
                        'audio' => (bool) $request->get('audio'),
                        'auto_recording' => (bool) $request->get('auto_recording'),
                        'join_before_host' => (bool) $request->get('join_before_host'),
                        'meeting_authentication' => (bool) $request->get('meeting_authentication'),
                        'mute_upon_entry' => (bool) $request->get('mute_upon_entry'),
                    ],
                ];
                // call api to create zoom
                $zoom = new ZoomService(env('ZOOM_API_KEY'), env('ZOOM_API_SECRET'));
                $response = $zoom->doRequest('POST', sprintf('/users/%s/meetings', env('ZOOM_USER_ID')), [], [], $data);

                if ($response === false || isset($response['code'])) {
                    // There was an error before the request was event sent to the api
                    return $this->json($response);
                } else {
                    $startTime = new DateTime($response['start_time']);

                    return $this->render('Block/schedule.twig', [
                        'startTime' => $startTime->format('Y-m-d H:i:s'),
                        'duration' => $response['duration'].'分',
                        'meetingId' => $response['id'],
                        'password' => $response['password'],
                        'meetingUrl' => $response['join_url'],
                    ]);
                }
            }
        }
    }

    /**
     * @Route("/seminar/schedule/detail", name="admin_seminar_schedule_delete", methods={"DELETE"})
     */
    public function deleteSchedule(Request $request): JsonResponse
    {
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            if ('DELETE' === $request->getMethod()) {
                $Schedule = $this->productSeminarRepository->find($request->get('id'));
                // call api to delete zoom
                $zoom = new ZoomService(env('ZOOM_API_KEY'), env('ZOOM_API_SECRET'));
                $response = $zoom->doRequest('DELETE', sprintf('/meetings/%s', (int) $Schedule->getZoomId()));
                if (isset($response['code'])) {
                    return $this->json($response);
                } else {
                    $this->entityManager->remove($Schedule);
                    $this->entityManager->flush();
                    $response['code'] = 204;

                    return $this->json($response);
                }
            }
        }
    }

    /**
     * 商品CSVの出力.
     *
     * @Route("/%eccube_admin_route%/seminar/export", name="admin_seminar_export")
     *
     * @return StreamedResponse
     */
    public function export(Request $request)
    {
        // タイムアウトを無効にする.
        set_time_limit(0);

        // sql loggerを無効にする.
        $em = $this->entityManager;
        $em->getConfiguration()->setSQLLogger(null);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($request) {
            // CSV種別を元に初期化.
            $this->csvExportService->initCsvType(CsvType::CSV_TYPE_PRODUCT);

            // ヘッダ行の出力.
            $this->csvExportService->exportHeader();

            // 商品データ検索用のクエリビルダを取得.
            $qb = $this->csvExportService
                ->getSeminarQueryBuilder($request);

            $qb->resetDQLPart('select')
                ->resetDQLPart('orderBy')
                ->orderBy('p.update_date', 'DESC');

            $qb->select('p')
                ->distinct();

            // データ行の出力.
            $this->csvExportService->setExportQueryBuilder($qb);

            $this->csvExportService->exportData(function ($entity, CsvExportService $csvService) use ($request) {
                $Csvs = $csvService->getCsvs();

                /** @var $Product Product */
                $Product = $entity;

                /** @var $ProductClasses ProductClass[] */
                $ProductClasses = $Product->getProductClasses();

                foreach ($ProductClasses as $ProductClass) {
                    $ExportCsvRow = new ExportCsvRow();

                    // CSV出力項目と合致するデータを取得.
                    foreach ($Csvs as $Csv) {
                        // 商品データを検索.
                        $ExportCsvRow->setData($csvService->getData($Csv, $Product));
                        if ($ExportCsvRow->isDataNull()) {
                            // 商品規格情報を検索.
                            $ExportCsvRow->setData($csvService->getData($Csv, $ProductClass));
                        }

                        $event = new EventArgs(
                            [
                                'csvService' => $csvService,
                                'Csv' => $Csv,
                                'ProductClass' => $ProductClass,
                                'ExportCsvRow' => $ExportCsvRow,
                            ],
                            $request
                        );
                        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_CSV_EXPORT, $event);

                        $ExportCsvRow->pushData();
                    }

                    // $row[] = number_format(memory_get_usage(true));
                    // 出力.
                    $csvService->fputcsv($ExportCsvRow->getRow());
                }
            });
        });

        $now = new \DateTime();
        $filename = 'seminar'.$now->format('YmdHis').'.csv';
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
        $response->send();

        log_info('商品CSV出力ファイル名', [$filename]);

        return $response;
    }

    /**
     * @Route("/%eccube_admin_route%/seminar/{id}/copy", requirements={"id" = "\d+"}, name="admin_seminar_copy", methods={"POST"})
     */
    public function copy(Request $request, $id = null)
    {
        $this->isTokenValid();

        if (!is_null($id)) {
            $Product = $this->seminarRepository->find($id);
            if ($Product instanceof Product) {
                $CopyProduct = clone $Product;
                $CopyProduct->copy();
                $ProductStatus = $this->productStatusRepository->find(ProductStatus::DISPLAY_HIDE);
                $CopyProduct->setStatus($ProductStatus);

                $CopyProductCategories = $CopyProduct->getProductCategories();
                foreach ($CopyProductCategories as $Category) {
                    $this->entityManager->persist($Category);
                }

                // 規格あり商品の場合は, デフォルトの商品規格を取得し登録する.
                if ($CopyProduct->hasProductClass()) {
                    $dummyClass = $this->productClassRepository->findOneBy([
                        'visible' => false,
                        'ClassCategory1' => null,
                        'ClassCategory2' => null,
                        'Product' => $Product,
                    ]);
                    $dummyClass = clone $dummyClass;
                    $dummyClass->setProduct($CopyProduct);
                    $CopyProduct->addProductClass($dummyClass);
                }

                $CopyProductClasses = $CopyProduct->getProductClasses();
                foreach ($CopyProductClasses as $Class) {
                    $TaxRule = $Class->getTaxRule();
                    if ($TaxRule) {
                        $CopyTaxRule = clone $TaxRule;
                        $CopyTaxRule->setProductClass($Class);
                        $CopyTaxRule->setProduct($CopyProduct);
                        $this->entityManager->persist($CopyTaxRule);
                    }
                    $this->entityManager->persist($Class);
                }
                $Images = $CopyProduct->getProductImage();
                foreach ($Images as $Image) {
                    // 画像ファイルを新規作成
                    $extension = pathinfo($Image->getFileName(), PATHINFO_EXTENSION);
                    $filename = date('mdHis').uniqid('_').'.'.$extension;
                    try {
                        $fs = new Filesystem();
                        $fs->copy($this->eccubeConfig['eccube_save_image_dir'].'/'.$Image->getFileName(), $this->eccubeConfig['eccube_save_image_dir'].'/'.$filename);
                    } catch (\Exception $e) {
                        // エラーが発生しても無視する
                    }
                    $Image->setFileName($filename);

                    $this->entityManager->persist($Image);
                }
                $Tags = $CopyProduct->getProductTag();
                foreach ($Tags as $Tag) {
                    $this->entityManager->persist($Tag);
                }

                $Schedules = $CopyProduct->getProductSeminar();
                foreach ($Schedules as $Schedule) {
                    $this->entityManager->persist($Schedule);
                }

                $this->entityManager->persist($CopyProduct);

                $this->entityManager->flush();

                $event = new EventArgs(
                    [
                        'Product' => $Product,
                        'CopyProduct' => $CopyProduct,
                        'CopyProductCategories' => $CopyProductCategories,
                        'CopyProductClasses' => $CopyProductClasses,
                        'images' => $Images,
                        'Tags' => $Tags,
                        'Schedules' => $Schedules,
                    ],
                    $request
                );
                $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_COPY_COMPLETE, $event);

                $this->addSuccess('admin.seminar.copy_complete', 'admin');

                return $this->redirectToRoute('admin_seminar_edit', ['id' => $CopyProduct->getId()]);
            } else {
                $this->addError('admin.seminar.copy_error', 'admin');
            }
        } else {
            $msg = trans('admin.seminar.copy_error');
            $this->addError($msg, 'admin');
        }

        return $this->redirectToRoute('admin_seminar');
    }

    /**
     * Bulk public action
     *
     * @Route("/%eccube_admin_route%/seminar/bulk/seminar-status/{id}", requirements={"id" = "\d+"}, name="admin_seminar_bulk_seminar_status", methods={"POST"})
     *
     * @return RedirectResponse
     */
    public function bulkProductStatus(Request $request, ProductStatus $ProductStatus, CacheUtil $cacheUtil)
    {
        $this->isTokenValid();

        /** @var Product[] $Products */
        $Products = $this->seminarRepository->findBy(['id' => $request->get('ids')]);
        $count = 0;
        foreach ($Products as $Product) {
            try {
                $Product->setStatus($ProductStatus);
                $this->productRepository->save($Product);
                $count++;
            } catch (\Exception $e) {
                $this->addError($e->getMessage(), 'admin');
            }
        }
        try {
            if ($count) {
                $this->entityManager->flush();
                $msg = $this->translator->trans('admin.product.bulk_change_status_complete', [
                    '%count%' => $count,
                    '%status%' => $ProductStatus->getName(),
                ]);
                $this->addSuccess($msg, 'admin');
                $cacheUtil->clearDoctrineCache();
            }
        } catch (\Exception $e) {
            $this->addError($e->getMessage(), 'admin');
        }

        return $this->redirectToRoute('admin_seminar', ['resume' => Constant::ENABLED]);
    }

    /**
     * @Route("/%eccube_admin_route%/seminar/{id}/delete", requirements={"id" = "\d+"}, name="admin_seminar_delete", methods={"DELETE"})
     *
     * @param null $id
     *
     * @return JsonResponse|RedirectResponse
     *
     * @throws Exception
     */
    public function delete(Request $request, $id = null, CacheUtil $cacheUtil)
    {
        $this->isTokenValid();
        $session = $request->getSession();
        $page_no = intval($session->get('eccube.admin.product.search.page_no'));
        $page_no = $page_no ? $page_no : Constant::ENABLED;
        $message = null;
        $success = false;

        if (!is_null($id)) {
            /* @var $Product \Eccube\Entity\Product */
            $Product = $this->seminarRepository->find($id);
            if (!$Product) {
                if ($request->isXmlHttpRequest()) {
                    $message = trans('admin.common.delete_error_already_deleted');

                    return $this->json(['success' => $success, 'message' => $message]);
                } else {
                    $this->deleteMessage();
                    $rUrl = $this->generateUrl('admin_seminar_page', ['page_no' => $page_no]).'?resume='.Constant::ENABLED;

                    return $this->redirect($rUrl);
                }
            }

            if ($Product instanceof Product) {
                log_info('商品削除開始', [$id]);

                $deleteImages = $Product->getProductImage();
                $ProductClasses = $Product->getProductClasses();

                try {
                    $this->seminarRepository->delete($Product);
                    $this->entityManager->flush();

                    $event = new EventArgs(
                        [
                            'Product' => $Product,
                            'ProductClass' => $ProductClasses,
                            'deleteImages' => $deleteImages,
                        ],
                        $request
                    );
                    $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_DELETE_COMPLETE, $event);
                    $deleteImages = $event->getArgument('deleteImages');

                    // 画像ファイルの削除(commit後に削除させる)
                    /** @var ProductImage $deleteImage */
                    foreach ($deleteImages as $deleteImage) {
                        if ($this->productImageRepository->findOneBy(['file_name' => $deleteImage->getFileName()])) {
                            continue;
                        }
                        try {
                            $fs = new Filesystem();
                            $fs->remove($this->eccubeConfig['eccube_save_image_dir'].'/'.$deleteImage);
                        } catch (\Exception $e) {
                            // エラーが発生しても無視する
                        }
                    }

                    log_info('商品削除完了', [$id]);

                    $success = true;
                    $message = trans('admin.common.delete_complete');

                    $cacheUtil->clearDoctrineCache();
                } catch (ForeignKeyConstraintViolationException $e) {
                    log_info('商品削除エラー', [$id]);
                    $message = trans('admin.common.delete_error_foreign_key', ['%name%' => $Product->getName()]);
                }
            } else {
                log_info('商品削除エラー', [$id]);
                $message = trans('admin.common.delete_error');
            }
        } else {
            log_info('商品削除エラー', [$id]);
            $message = trans('admin.common.delete_error');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => $success, 'message' => $message]);
        } else {
            if ($success) {
                $this->addSuccess($message, 'admin');
            } else {
                $this->addError($message, 'admin');
            }

            $rUrl = $this->generateUrl('admin_seminar_page', ['page_no' => $page_no]).'?resume='.Constant::ENABLED;

            return $this->redirect($rUrl);
        }
    }

    /**
     * @Route("/seminar/send/mail_schedule", name="admin_seminar_send_url_schedule", methods={"POST"})
     */
    public function sendMailSchedule(Request $request): JsonResponse
    {
        $response = [];
        if ($request->isXmlHttpRequest() && $this->isTokenValid()) {
            if ('POST' === $request->getMethod()) {
                //find schedule
                $Schedule = $this->productSeminarRepository->find($request->get('id'));

                $ListOrderItem = $this->OrderItemRepository->findBy(['Product' => $Schedule->getProduct()]);

                if ($ListOrderItem) {
                    foreach ($ListOrderItem as $OrderItem) {
                        try {
                            $this->mailService->sendUrlMeetingMail($OrderItem->getOrder(), $OrderItem, $Schedule);
                            $response['code'] = 204;
                            $response['message'] = 'ワークショップ情報がクライアントに送信されました';
                        } catch (LoaderError | RuntimeError | SyntaxError $e) {
                            $response['code'] = 400;
                            $response['message'] = $e->getMessage();
                        }
                    }
                } else {
                    $response['message'] = 'このセミナーに登録するお客様はまだいないため、メールを送信できません';
                }
            }
        }

        return $this->json($response);
    }
}
