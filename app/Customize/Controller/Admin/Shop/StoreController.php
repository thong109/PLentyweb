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

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Customize\Entity\Store;
use Customize\Repository\StoreRepository;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\CsvType;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Customize\Form\Type\Admin\StoreType;
use Eccube\Repository\Master\PageMaxRepository;
use Eccube\Service\CsvExportService;
use Eccube\Util\CacheUtil;
use Eccube\Util\FormUtil;
use Knp\Component\Pager\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\TranslatorInterface;
use Eccube\Common\Constant;

class StoreController extends AbstractController
{
    /**
     * @var
     */
    protected $storeRepository;

    /**
     * @var PageMaxRepository
     */
    protected $pageMaxRepository;
    private $csvExportService;

    public function __construct(
        StoreRepository $storeRepository,
        PageMaxRepository $pageMaxRepository,
        CsvExportService $csvExportService
    ) {
        $this->pageMaxRepository = $pageMaxRepository;
        $this->storeRepository = $storeRepository;
        $this->csvExportService = $csvExportService;
    }

    /**
     * @Route("/%eccube_admin_route%/shop", name="admin_shop")
     * @Route("/%eccube_admin_route%/shop/page/{page_no}", requirements={"page_no" = "\d+"}, name="admin_shop_page")
     * @Template("@admin/Shop/index.twig")
     */
    public function index(Request $request, $page_no = null, Paginator $paginator)
    {
        $session = $this->session;
        $pageMaxis = $this->pageMaxRepository->findAll();
        $pageCount = $session->get('eccube.admin.customer.search.page_count', $this->eccubeConfig['eccube_default_page_count']);
        $pageCountParam = $request->get('page_count');

        if ($pageCountParam && is_numeric($pageCountParam)) {
            foreach ($pageMaxis as $pageMax) {
                if ($pageCountParam == $pageMax->getName()) {
                    $pageCount = $pageMax->getName();
                    $session->set('eccube.admin.store.search.page_count', $pageCount);
                    break;
                }
            }
        }

        if (null !== $page_no || $request->get('resume')) {
            if ($page_no) {
                $session->set('eccube.admin.store.search.page_no', (int) $page_no);
            } else {
                $page_no = $session->get('eccube.admin.store.search.page_no', 1);
            }
        } else {
            $page_no = 1;
            $session->set('eccube.admin.store.search.page_no', $page_no);
        }

        $qb = $this->storeRepository->getQueryBuilderBySearchData();

        $pagination = $paginator->paginate(
            $qb,
            $page_no,
            $pageCount
        );

        return [
            'pagination' => $pagination,
            'pageMaxis' => $pageMaxis,
            'page_no' => $page_no,
            'page_count' => $pageCount,
            'has_errors' => false,
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/shop/new", name="admin_shop_new")
     * @Route("/%eccube_admin_route%/shop/{id}/edit", requirements={"id" = "\d+"}, name="admin_shop_edit")
     * @Template("@admin/Shop/shop.twig")
     */
    public function edit(Request $request, $id = null, CacheUtil $cacheUtil)
    {
        if (is_null($id)) {
            $Store = new Store();
        } else {
            $Store = $this->storeRepository->find($id);
            if (!$Store) {
                throw new NotFoundHttpException();
            }
        }

        $builder = $this->formFactory
            ->createBuilder(StoreType::class, $Store);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Store' => $Store,
            ],
            $request
        );

        $this->eventDispatcher->dispatch('admin.shop.edit.initialize', $event);

        $form = $builder->getForm();
        $form->handleRequest($request);
        // ファイルの登録
        if (!is_null($id) && !$form->isSubmitted()) {
            $image = $Store->getImage();
            $images[0] = $image;
            $form['images']->setData($images);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // add images
            $add_images = $form->get('add_images')->getData();
            if (!empty($add_images)) {
                $imageStore = array_values($add_images);
                $Store->setImage(isset($imageStore[0]) ? $imageStore[0] : '');

                $file = new File($this->eccubeConfig['eccube_temp_image_dir'].'/'.$imageStore[0]);
                $file->move($this->eccubeConfig['eccube_save_image_dir']);
            }

            $this->entityManager->persist($Store);
            $this->entityManager->flush();

            $this->addSuccess('admin.common.save_complete', 'admin');

            $cacheUtil->clearDoctrineCache();

            return $this->redirectToRoute('admin_shop_edit', [
                'id' => $Store->getId(),
            ]);
        }

        return [
            'Store' => $Store,
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/%eccube_admin_route%/shop/image/add", name="admin_shop_image_add", methods={"POST"})
     */
    public function addImage(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException();
        }

        $images = $request->files->get('admin_store');
        $allowExtensions = ['gif', 'jpg', 'jpeg', 'png'];
        $files = [];

        if (count($images) > 0) {
            foreach ($images as $img) {
                foreach ($img as $image) {
                    //ファイルフォーマット検証
                    $mimeType = $image->getMimeType();
                    if (0 !== strpos($mimeType, 'image')) {
                        throw new UnsupportedMediaTypeHttpException();
                    }

                    // 拡張子
                    $extension = $image->getClientOriginalExtension();
                    if (!in_array(strtolower($extension), $allowExtensions)) {
                        throw new UnsupportedMediaTypeHttpException();
                    }

                    $filename = date('mdHis').uniqid('_').'.'.$extension;
                    $image->move($this->eccubeConfig['eccube_temp_image_dir'], $filename);
                    $files[] = $filename;
                }
            }
        }

        $event = new EventArgs(
            [
                'images' => $images,
                'files' => $files,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_PRODUCT_ADD_IMAGE_COMPLETE, $event);
        $files = $event->getArgument('files');

        return $this->json(['files' => $files], 200);
    }

    /**
     * @Route("/%eccube_admin_route%/shop/{id}/delete", requirements={"id" = "\d+"}, name="admin_shop_delete", methods={"DELETE"})
     */
    public function delete(Request $request, $id, TranslatorInterface $translator)
    {
        $this->isTokenValid();

        log_info('会員削除開始', [$id]);

        $page_no = intval($this->session->get('eccube.admin.store.search.page_no'));
        $page_no = $page_no ? $page_no : Constant::ENABLED;

        $store = $this->storeRepository->find($id);

        if (!$store) {
            $this->deleteMessage();

            return $this->redirect($this->generateUrl('admin_shop_page',
                    ['page_no' => $page_no]).'?resume='.Constant::ENABLED);
        }

        try {
            $this->entityManager->remove($store);
            $this->entityManager->flush($store);
            $this->addSuccess('admin.common.delete_complete', 'admin');
        } catch (ForeignKeyConstraintViolationException $e) {
            log_error('会員削除失敗', [$e]);

            $message = trans('admin.common.delete_error_foreign_key', ['%name%' => $store->getName]);
            $this->addError($message, 'admin');
        }

        log_info('会員削除完了', [$id]);

        $event = new EventArgs(
            [
                'Store' => $store,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_CUSTOMER_DELETE_COMPLETE, $event);

        return $this->redirect($this->generateUrl('admin_shop_page',
                ['page_no' => $page_no]).'?resume='.Constant::ENABLED);
    }


    /**
     * 会員CSVの出力.
     *
     * @Route("/%eccube_admin_route%/shop/export", name="admin_shop_export")
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
            $this->csvExportService->initCsvType(CsvType::CSV_TYPE_SHOP);

            // ヘッダ行の出力.
            $this->csvExportService->exportHeader();
            // 会員データ検索用のクエリビルダを取得.
            $qb = $this->csvExportService
                ->getShopQueryBuilder($request);

            // データ行の出力.
            $this->csvExportService->setExportQueryBuilder($qb);
            $this->csvExportService->exportData(function ($entity, $csvService) use ($request) {
                $Csvs = $csvService->getCsvs();

                /** @var $Store \Customize\Entity\Store */
                $Store = $entity;

                $ExportCsvRow = new \Eccube\Entity\ExportCsvRow();

                // CSV出力項目と合致するデータを取得.
                foreach ($Csvs as $Csv) {
                    // 会員データを検索.
                    $ExportCsvRow->setData($csvService->getData($Csv, $Store));

                    $event = new EventArgs(
                        [
                            'csvService' => $csvService,
                            'Csv' => $Csv,
                            'Customer' => $Store,
                            'ExportCsvRow' => $ExportCsvRow,
                        ],
                        $request
                    );
                    $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_CUSTOMER_CSV_EXPORT, $event);

                    $ExportCsvRow->pushData();
                }

                //$row[] = number_format(memory_get_usage(true));
                // 出力.
                $csvService->fputcsv($ExportCsvRow->getRow());
            });
        });

        $now = new \DateTime();
        $filename = 'shop_'.$now->format('YmdHis').'.csv';
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

        $response->send();

        log_info('会員CSVファイル名', [$filename]);

        return $response;
    }

}
