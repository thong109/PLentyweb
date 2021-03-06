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

namespace Customize\Controller\Admin\Product;

use Customize\Entity\ProductEntity;
use Customize\Entity\ProductVideo;
use Customize\Form\Type\Admin\ProductType;
use Customize\Form\Type\Admin\ProductTypeWithRental;
use Customize\Repository\ProductVideoRepository;
use Customize\Repository\StoreRepository;
use Eccube\Controller\AbstractController;
use Eccube\Entity\BaseInfo;
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
use Customize\Form\Type\Admin\SearchProductType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CategoryRepository;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Repository\Master\ProductStatusRepository;
use Eccube\Repository\ProductClassRepository;
use Eccube\Repository\ProductImageRepository;
use Customize\Repository\ProductRepository;
use Customize\Repository\StoreMemberRepository;
use Eccube\Repository\TagRepository;
use Eccube\Repository\TaxRuleRepository;
use Eccube\Service\CsvExportService;
use Eccube\Util\CacheUtil;
use Eccube\Util\FormUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Knp\Component\Pager\Paginator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProductController extends AbstractController
{
    /**
     * @var ProductClassRepository
     */
    protected $productClassRepository;

    /**
     * @var ProductImageRepository
     */
    protected $productImageRepository;

    /**
     * @var ProductVideoRepository
     */
    protected $productVideoRepository;

    /**
     * @var TaxRuleRepository
     */
    protected $taxRuleRepository;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var ProductStatusRepository
     */
    protected $productStatusRepository;


    /**
     * @var StoreRepository
     */
    protected $storeRepository;

    /**
     * @var TagRepository
     */
    protected $tagRepository;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;

    /**
     * @var StoreMemberRepository
     */
    protected $storeMemberRepository;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    private $csvExportService;

    /**
     * ProductController constructor.
     */
    public function __construct(
        ProductClassRepository $productClassRepository,
        ProductImageRepository $productImageRepository,
        ProductVideoRepository $productVideoRepository,
        TaxRuleRepository $taxRuleRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        BaseInfoRepository $baseInfoRepository,
        ProductStatusRepository $productStatusRepository,
        TagRepository $tagRepository,
        PageMaxRepository $pageMaxRepository,
        StoreRepository $storeRepository,
        TokenStorageInterface $tokenStorage,
        StoreMemberRepository $storeMemberRepository,
        CsvExportService $csvExportService
    ) {
        $this->csvExportService = $csvExportService;
        $this->productClassRepository = $productClassRepository;
        $this->productImageRepository = $productImageRepository;
        $this->productVideoRepository = $productVideoRepository;
        $this->taxRuleRepository = $taxRuleRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->productStatusRepository = $productStatusRepository;
        $this->tagRepository = $tagRepository;
        $this->pageMaxRepository = $pageMaxRepository;
        $this->storeRepository = $storeRepository;
        $this->tokenStorage = $tokenStorage;
        $this->storeMemberRepository = $storeMemberRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/product", name="admin_product")
     * @Route("/%eccube_admin_route%/product/page/{page_no}", requirements={"page_no" = "\d+"}, name="admin_product_page")
     * @Template("@admin/Product/index.twig")
     */
    public function index(Request $request, $page_no = null, Paginator $paginator)
    {
        $builder = $this->formFactory
            ->createBuilder(SearchProductType::class);

        $event = new EventArgs(
            [
                'builder' => $builder,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_INDEX_INITIALIZE, $event);

        $searchForm = $builder->getForm();

        /**
         * ???????????????????????????, ??????????????????????????????.
         * - ??????????????????????????????
         * - ???????????????
         * - ??????????????????
         * ??????, ???????????????????????????????????? mtb_page_max????????????, ????????????????????????????????????.
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
                 * ?????????????????????????????????, ?????????????????????????????????????????????.
                 * ????????????????????????????????????????????????????????????.
                 */
                $page_no = 1;
                $searchData = $searchForm->getData();

                // ????????????, ??????????????????????????????????????????.
                $this->session->set('eccube.admin.product.search', FormUtil::getViewData($searchForm));
                $this->session->set('eccube.admin.product.search.page_no', $page_no);
            } else {

                // ????????????????????????, ????????????????????????????????????????????????.
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
                 * ???????????????????????????????????????????????????????????????????????????, ????????????????????????????????????????????????.
                 */
                if ($page_no) {
                    // ????????????????????????????????????.
                    $this->session->set('eccube.admin.product.search.page_no', (int) $page_no);
                } else {
                    // ?????????????????????????????????.
                    $page_no = $this->session->get('eccube.admin.product.search.page_no', 1);
                }
                $viewData = $this->session->get('eccube.admin.product.search', []);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

            } else {
                /**
                 * ?????????????????????.
                 */
                $page_no = 1;
                // submit default value
                $viewData = FormUtil::getViewData($searchForm);
                $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

                // ?????????????????????????????????, ???????????????????????????.
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

        $qb = $this->productRepository->getQueryBuilderBySearchDataForAdmin($searchData);

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
            'sale_type_id' => $searchForm->getData()['sale_type_id']
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/product/product/new", name="admin_product_product_new")
     * @Route("/%eccube_admin_route%/product/product/{id}/edit", requirements={"id" = "\d+"}, name="admin_product_product_edit")
     * @Template("@admin/Product/product.twig")
     */
    public function edit(Request $request, $id = null, RouterInterface $router, CacheUtil $cacheUtil)
    {
        $has_class = false;
        $sale_type = 1;

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
            // ????????????????????????????????????????????????????????????????????????????????????
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
                $sale_type = $ProductClass->getSaleType()->getId();
            }
        }
        $builder = $this->formFactory
            ->createBuilder(ProductType::class, $Product);

        // ???????????????????????????????????????????????????Form????????????
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
            $ProductClass->setStockUnlimited($ProductClass->isStockUnlimited());
            $form['class']->setData($ProductClass);
        }

        // ?????????????????????
        $images = [];
        $ProductImages = $Product->getProductImage();
        foreach ($ProductImages as $ProductImage) {
            $images[] = $ProductImage->getFileName();
        }

        $form['images']->setData($images);

        $categories = [];
        $ProductCategories = $Product->getProductCategories();
        foreach ($ProductCategories as $ProductCategory) {
            /* @var $ProductCategory \Eccube\Entity\ProductCategory */
            $categories[] = $ProductCategory->getCategory();
        }
        $form['Category']->setData($categories);
        $Tags = $Product->getTags();
        $form['Tag']->setData($Tags);
        $error = false;
        $errorEclink = false;
        $errorRental = false;

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            //                save video
            // ???????????????
            $ecLink = $Product->getEcLink();
            $rentalMinDay = $Product->getRentalMin();

            if (isset($request->request->get('admin_product')['class']) &&
                isset($request->request->get('admin_product')['class']['sale_type'])) {
                $sale_type = $request->request->get('admin_product')['class']['sale_type'];

                if (isset($request->request->get('admin_product')['class']) && $sale_type == ProductEntity::VIDEO_PRODUCT) {
                    $video_links = $request->request->get('video')['link'];
                    $videos_names = $request->request->get('video')['name'];
                    $videos_ids = $request->request->get('video')['id'];
                    $videos_use = 1;
                    if ($video_links !== null){
                        foreach ($video_links as $key => $video_link) {
                            $ProductVideo = new \Customize\Entity\ProductVideo();


                            if ($video_links[$key] == '' || $videos_names[$key] == '') {
                                $error = true;
                            }
                            if ($videos_ids[$key] != 0) {
                                $ProductVideo = $this->productVideoRepository->find($videos_ids[$key]);
                            }

                            $ProductVideo
                                ->setVideoLink($video_links[$key])
                                ->setVideoName($videos_names[$key])
                                ->setVideoPrice(0)
                                ->setInUse($videos_use)
                                ->setProduct($Product);
                            $this->entityManager->persist($ProductVideo);
                        }
                    } else {
                        $error = true;
                    }
                } elseif ($sale_type == ProductEntity::ECLINK_PRODUCT && $ecLink == null) {
                    $error = true;
                } elseif ($sale_type == ProductEntity::RENTAL_PRODUCT) {
                    if($rentalMinDay == null){
                        $errorRental = true;
                    }elseif ($rentalMinDay <= 0){
                        $errorRental = true;
                    }
                }
            }
            if ($form->isValid() && $errorRental == false && $error == false) {
                log_info('??????????????????', [$id]);

                $Product = $form->getData();
                if (!$has_class) {
                    $ProductClass = $form['class']->getData();
                    $Product->setEcLink($ecLink);
                    $Product->setRentalMin($rentalMinDay == null ? $rentalMinDay : (int)$rentalMinDay);
                    $this->entityManager->persist($Product);
                    $this->entityManager->flush();

                    // ???????????????
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
                    $ProductStock = new ProductStock();
                    $ProductStock->setProductClass($ProductClass);
                    // ?????????????????????
                    if (!$ProductClass->isStockUnlimited()) {
                        $ProductStock->setStock($ProductClass->getStock());
                    } else {
                        // ?????????????????????null?????????
                        $ProductStock->setStock(null);
                    }
                    $this->entityManager->persist($ProductStock);
                }
                $ecLink = $Product->getEcLink();
                $Product->setEcLink($ecLink);
                $this->entityManager->persist($Product);
                $this->entityManager->flush();

                // ?????????????????????
                // ???????????????
                /* @var $Product \Eccube\Entity\Product */
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
                            /* @var $Product \Eccube\Entity\Product */
                            $Product->addProductCategory($ProductCategory);
                            $categoriesIdList[$ParentCategory->getId()] = true;
                        }
                    }
                    if (!isset($categoriesIdList[$Category->getId()])) {
                        $ProductCategory = $this->createProductCategory($Product, $Category, $count);
                        $this->entityManager->persist($ProductCategory);
                        $count++;
                        /* @var $Product \Eccube\Entity\Product */
                        $Product->addProductCategory($ProductCategory);
                        $categoriesIdList[$ParentCategory->getId()] = true;
                    }
                }

                // ???????????????
                $add_images = $form->get('add_images')->getData();
                foreach ($add_images as $add_image) {
                    $ProductImage = new \Eccube\Entity\ProductImage();
                    $ProductImage
                        ->setFileName($add_image)
                        ->setProduct($Product)
                        ->setSortNo(1);
                    $Product->addProductImage($ProductImage);
                    $this->entityManager->persist($ProductImage);

                    // ??????
                    $file = new File($this->eccubeConfig['eccube_temp_image_dir'].'/'.$add_image);
                    $file->move($this->eccubeConfig['eccube_save_image_dir']);
                }

                // ???????????????
                $delete_images = $form->get('delete_images')->getData();
                foreach ($delete_images as $delete_image) {
                    $ProductImage = $this->productImageRepository
                        ->findOneBy(['file_name' => $delete_image]);

                    // ?????????????????????????????????????????????Entity?????????????????????
                    if ($ProductImage instanceof ProductImage) {
                        $Product->removeProductImage($ProductImage);
                        $this->entityManager->remove($ProductImage);
                    }
                    $this->entityManager->persist($Product);
                    $this->entityManager->flush();

                    if (!$this->productImageRepository->findOneBy(['file_name' => $delete_image])) {
                        // ??????
                        $fs = new Filesystem();
                        $fs->remove($this->eccubeConfig['eccube_save_image_dir'].'/'.$delete_image);
                    }
                }

                $this->entityManager->persist($Product);
                $this->entityManager->flush();

                // ???????????????
                if (isset($request->request->get('video')['remove'])){
                    $delete_videos = $request->request->get('video')['remove'];

                    foreach ($delete_videos as $delete_video) {
                        if ($delete_video !== ''){
                            $ProductVideo = $this->productVideoRepository
                                ->findOneById(['id' => $delete_video]);
                            if (!$ProductVideo) {
                                throw $this->createNotFoundException('No found for id '.$delete_video);
                            }
                            $this->entityManager->remove($ProductVideo);
                            $this->entityManager->flush();
                        }
                    }
                }


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

                // ?????????????????????
                // ??????????????????????????????
                $ProductTags = $Product->getProductTag();
                foreach ($ProductTags as $ProductTag) {
                    $Product->removeProductTag($ProductTag);
                    $this->entityManager->remove($ProductTag);
                }

                // ?????????????????????
                $Tags = $form->get('Tag')->getData();
                foreach ($Tags as $Tag) {
                    $ProductTag = new ProductTag();
                    $ProductTag
                        ->setProduct($Product)
                        ->setTag($Tag);
                    $Product->addProductTag($ProductTag);
                    $this->entityManager->persist($ProductTag);
                }

                $Product->setUpdateDate(new \DateTime());
                $this->entityManager->flush();

                log_info('??????????????????', [$id]);

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
                        // $returnLink???path????????????????????????. path???????????????????????????????????????????????????.
                        $pattern = '/^'.preg_quote($request->getBasePath(), '/').'/';
                        $returnLink = preg_replace($pattern, '', $returnLink);
                        $result = $router->match($returnLink);
                        // ???????????????????????????
                        $params = array_filter($result, function ($key) {
                            return 0 !== \strpos($key, '_');
                        }, ARRAY_FILTER_USE_KEY);

                        // path??????url????????????????????????????????????.
                        return $this->redirectToRoute($result['_route'], $params);
                    } catch (\Exception $e) {
                        // ?????????????????????????????????????????????????????????.
                        log_warning('URL???????????????????????????');
                    }
                }

                $cacheUtil->clearDoctrineCache();

                return $this->redirectToRoute('admin_product_product_edit', ['id' => $Product->getId()]);
            }
        }

        // ?????????????????????
        $builder = $this->formFactory
            ->createBuilder(SearchProductType::class);

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

        // ??????????????????????????????????????????????????????????????????
        $TopCategories = $this->categoryRepository->getList(null);
        $ChoicedCategoryIds = array_map(function ($Category) {
            return $Category->getId();
        }, $form->get('Category')->getData());

        $videos = [];
        $ProductVideos = $this->productVideoRepository
            ->findBy([
                'Product' => $Product,
            ]);
        foreach ($ProductVideos as $ProductVideo) {
            $videos[] = [
                'id' => $ProductVideo->getId(),
                'link' => $ProductVideo->getVideoLink(),
                'name' => $ProductVideo->getVideoName(),
                'price' => $ProductVideo->getVideoPrice(),
                'use' => $ProductVideo->getInUse(),
            ];
        }

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
            'videos' => $videos,
            'error' => $error,
            'errorEclink' => $errorEclink,
            'sale_type' => $sale_type,
            'error_rental' => $errorRental
        ];
    }


    /**
     * ??????CSV?????????.
     *
     * @Route("/%eccube_admin_route%/product/export", name="admin_product_export")
     *
     * @return StreamedResponse
     */
    public function export(Request $request)
    {
        // ????????????????????????????????????.
        set_time_limit(0);

            // sql logger??????????????????.
        $em = $this->entityManager;
        $em->getConfiguration()->setSQLLogger(null);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($request) {
            // CSV????????????????????????.
            $this->csvExportService->initCsvType(CsvType::CSV_TYPE_PRODUCT);

            // ?????????????????????.
            $this->csvExportService->exportHeader();

            // ??????????????????????????????????????????????????????.
            $qb = $this->csvExportService
                ->getProductQueryBuilder($request);

            // Get stock status
            $isOutOfStock = 0;
            $session = $request->getSession();
            if ($session->has('eccube.admin.product.search')) {
                $searchData = $session->get('eccube.admin.product.search', []);
                if (isset($searchData['stock_status']) && $searchData['stock_status'] === 0) {
                    $isOutOfStock = 1;
                }
            }

            // join???????????????iterate?????????????????????, select??????distinct??????.
            // http://qiita.com/suin/items/2b1e98105fa3ef89beb7
            // distinct???mysql???pgsql????????????????????????.
            // http://uedatakeshi.blogspot.jp/2010/04/distinct-oeder-by-postgresmysql.html
            $qb->resetDQLPart('select')
                ->resetDQLPart('orderBy')
                ->orderBy('p.update_date', 'DESC');

            if ($isOutOfStock) {
                $qb->select('p, pc')
                    ->distinct();
            } else {
                $qb->select('p')
                    ->distinct();
            }
            // ?????????????????????.
            $this->csvExportService->setExportQueryBuilder($qb);

            $this->csvExportService->exportData(function ($entity, CsvExportService $csvService) use ($request) {
                $Csvs = $csvService->getCsvs();

                /** @var $Product \Eccube\Entity\Product */
                $Product = $entity;

                /** @var $ProductClasses \Eccube\Entity\ProductClass[] */
                $ProductClasses = $Product->getProductClasses();

                foreach ($ProductClasses as $ProductClass) {
                    $ExportCsvRow = new ExportCsvRow();

                    // CSV?????????????????????????????????????????????.
                    foreach ($Csvs as $Csv) {
                        // ????????????????????????.
                        $ExportCsvRow->setData($csvService->getData($Csv, $Product));
                        if ($ExportCsvRow->isDataNull()) {
                            // ???????????????????????????.
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
                    // ??????.
                    $csvService->fputcsv($ExportCsvRow->getRow());
                }
            });
        });

        $now = new \DateTime();
        $filename = 'product_'.$now->format('YmdHis').'.csv';
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
        $response->send();

        log_info('??????CSV?????????????????????', [$filename]);

        return $response;
    }

    /**
     * ProductCategory??????
     *
     * @param \Eccube\Entity\Product $Product
     * @param \Eccube\Entity\Category $Category
     * @param integer $count
     *
     * @return \Eccube\Entity\ProductCategory
     */
    private function createProductCategory($Product, $Category, $count)
    {
        $ProductCategory = new ProductCategory();
        $ProductCategory->setProduct($Product);
        $ProductCategory->setProductId($Product->getId());
        $ProductCategory->setCategory($Category);
        $ProductCategory->setCategoryId($Category->getId());

        return $ProductCategory;
    }
}
