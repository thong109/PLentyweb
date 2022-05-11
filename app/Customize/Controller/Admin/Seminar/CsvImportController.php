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

use Eccube\Controller\Admin\AbstractCsvImportController;
use Eccube\Entity\BaseInfo;
use Eccube\Entity\Product;
use Eccube\Form\Type\Admin\CsvImportType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CategoryRepository;
use Eccube\Repository\ClassCategoryRepository;
use Eccube\Repository\DeliveryDurationRepository;
use Eccube\Repository\Master\ProductStatusRepository;
use Eccube\Repository\Master\SaleTypeRepository;
use Eccube\Repository\ProductImageRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Repository\TagRepository;
use Eccube\Repository\TaxRuleRepository;
use Eccube\Util\CacheUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CsvImportController extends AbstractCsvImportController
{
    /**
     * @var DeliveryDurationRepository
     */
    protected $deliveryDurationRepository;

    /**
     * @var SaleTypeRepository
     */
    protected $saleTypeRepository;

    /**
     * @var TagRepository
     */
    protected $tagRepository;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * @var ClassCategoryRepository
     */
    protected $classCategoryRepository;

    /**
     * @var ProductImageRepository
     */
    protected $productImageRepository;

    /**
     * @var ProductStatusRepository
     */
    protected $productStatusRepository;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var TaxRuleRepository
     */
    private $taxRuleRepository;

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    private $errors = [];

    /**
     * CsvImportController constructor.
     *
     * @throws \Exception
     */
    public function __construct(
        DeliveryDurationRepository $deliveryDurationRepository,
        SaleTypeRepository $saleTypeRepository,
        TagRepository $tagRepository,
        CategoryRepository $categoryRepository,
        ClassCategoryRepository $classCategoryRepository,
        ProductImageRepository $productImageRepository,
        ProductStatusRepository $productStatusRepository,
        ProductRepository $productRepository,
        TaxRuleRepository $taxRuleRepository,
        BaseInfoRepository $baseInfoRepository,
        ValidatorInterface $validator
    ) {
        $this->deliveryDurationRepository = $deliveryDurationRepository;
        $this->saleTypeRepository = $saleTypeRepository;
        $this->tagRepository = $tagRepository;
        $this->categoryRepository = $categoryRepository;
        $this->classCategoryRepository = $classCategoryRepository;
        $this->productImageRepository = $productImageRepository;
        $this->productStatusRepository = $productStatusRepository;
        $this->productRepository = $productRepository;
        $this->taxRuleRepository = $taxRuleRepository;
        $this->BaseInfo = $baseInfoRepository->get();
        $this->validator = $validator;
    }

    /**
     * 商品登録CSVアップロード
     *
     * @Route("/%eccube_admin_route%/seminar/csv_upload", name="admin_seminar_csv_import")
     * @Template("@admin/Seminar/csv.twig")
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function csv(Request $request, CacheUtil $cacheUtil)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $headers = $this->getProductCsvHeader();
//        var_dump($headers);die;

        return $this->renderWithError($form, $headers);
    }

    /**
     * アップロード用CSV雛形ファイルダウンロード
     *
     * @Route("/%eccube_admin_route%/seminar/csv_template/{type}", requirements={"type" = "\w+"}, name="admin_seminar_csv_template")
     *
     * @param $type
     *
     * @return StreamedResponse
     */
    public function csvTemplate(Request $request, $type)
    {
        if ($type == 'product') {
            $headers = $this->getProductCsvHeader();
            $filename = 'product.csv';
            $response = new StreamedResponse();

            $response->setCallback(
                function () use ($headers, $request) {
                    $handle = fopen('php://output', 'r+');
                    $data = [
                        array_keys($headers),
                    ];
                    foreach (array_keys($headers) as $key => $value) {
                        $data[1][$key] = '';
                        if ($key == 12){
                            $data[1][$key] = $request->query->get('sale_type');
                        }
                    }
                    foreach ($data as $row) {
                        fputcsv($handle, $row);
                    }
                    fclose($handle);
                }
            );

            $response->headers->set('Content-Type', 'application/force-download');
            $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);

            return $response;
        } elseif ($type == 'category') {
            $headers = $this->getCategoryCsvHeader();
            $filename = 'category.csv';
        } else {
            throw new NotFoundHttpException();
        }

        return $this->sendTemplateResponse($request, array_keys($headers), $filename);
    }

    /**
     * 登録、更新時のエラー画面表示
     *
     * @param FormInterface $form
     * @param array $headers
     * @param bool $rollback
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    protected function renderWithError($form, $headers, $rollback = true)
    {
        if ($this->hasErrors()) {
            if ($rollback) {
                $this->entityManager->getConnection()->rollback();
            }
        }

        return [
            'form' => $form->createView(),
            'headers' => $headers,
            'errors' => $this->errors,
        ];
    }

    /**
     * 登録、更新時のエラー画面表示
     */
    protected function addErrors($message)
    {
        $this->errors[] = $message;
    }

    /**
     * @return array
     */
    protected function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return boolean
     */
    protected function hasErrors()
    {
        return count($this->getErrors()) > 0;
    }

    /**
     * 商品登録CSVヘッダー定義
     *
     * @return array
     */
    protected function getCategoryCsvHeader()
    {
        return [
            trans('admin.seminar.seminar_csv.user_id_col') => [
                'id' => 'user_id',
                'description' => 'admin.seminar.seminar_csv.user_id_description',
                'required' => false,
            ],
            trans('admin.seminar.seminar_csv.firstname_col') => [
                'id' => 'firstname',
                'description' => 'admin.seminar.seminar_csv.firstname_description',
                'required' => true,
            ],
            trans('admin.seminar.seminar_csv.lastname_col') => [
                'id' => 'lastname',
                'description' => 'admin.seminar.seminar_csv.lastname_description',
                'required' => true,
            ],

            trans('admin.seminar.seminar_csv.firstname_kana_col') => [
                'id' => 'firstname_kana',
                'description' => 'admin.seminar.seminar_csv.firstname_kana_description',
                'required' => true,
            ],
            trans('admin.seminar.seminar_csv.lastname_kana_col') => [
                'id' => 'lastname_kana',
                'description' => 'admin.seminar.seminar_csv.lastname_kana_description',
                'required' => true,
            ],

            trans('admin.seminar.seminar_csv.company_col') => [
                'id' => 'company',
                'description' => 'admin.seminar.seminar_csv.company_description',
                'required' => false,
            ],

            trans('admin.seminar.seminar_csv.zipcode_col') => [
                'id' => 'zipcode',
                'description' => 'admin.seminar.seminar_csv.zipcode_description',
                'required' => true,
            ],

            trans('admin.seminar.seminar_csv.province_id_col') => [
                'id' => 'province_id',
                'description' => 'admin.seminar.seminar_csv.province_id_description',
                'required' => true,
            ],
            trans('admin.seminar.seminar_csv.province_name_col') => [
                'id' => 'province_name',
                'description' => 'admin.seminar.seminar_csv.province_name_description',
                'required' => true,
            ],
            trans('admin.seminar.seminar_csv.add_1_col') => [
                'id' => 'add_1',
                'description' => 'admin.seminar.seminar_csv.add_1_description',
                'required' => true,
            ],
            trans('admin.seminar.seminar_csv.add_2_col') => [
                'id' => 'add_2',
                'description' => 'admin.seminar.seminar_csv.add_2_description',
                'required' => true,
            ],

            trans('admin.seminar.seminar_csv.email_col') => [
                'id' => 'email',
                'description' => 'admin.seminar.seminar_csv.email_description',
                'required' => true,
            ],

            trans('admin.seminar.seminar_csv.mobile_col') => [
                'id' => 'mobile',
                'description' => 'admin.seminar.seminar_csv.mobile_description',
                'required' => true,
            ],

            trans('admin.seminar.seminar_csv.sex_col') => [
                'id' => 'sex',
                'description' => 'admin.seminar.seminar_csv.sex_description',
                'required' => false,
            ],
            trans('admin.seminar.seminar_csv.sex_name_col') => [
                'id' => 'sex_name',
                'description' => 'admin.seminar.seminar_csv.sex_name_description',
                'required' => false,
            ],

            trans('admin.seminar.seminar_csv.job_col') => [
                'id' => 'job',
                'description' => 'admin.seminar.seminar_csv.job_description',
                'required' => false,
            ],
            trans('admin.seminar.seminar_csv.job_name_col') => [
                'id' => 'job_name',
                'description' => 'admin.seminar.seminar_csv.job_name_description',
                'required' => false,
            ],

            trans('admin.seminar.seminar_csv.birthday_col') => [
                'id' => 'birthday',
                'description' => 'admin.seminar.seminar_csv.birthday_description',
                'required' => false,
            ],

            trans('admin.seminar.seminar_csv.order_begin_col') => [
                'id' => 'order_begin',
                'description' => 'admin.seminar.seminar_csv.order_begin_description',
                'required' => false,
            ],

            trans('admin.seminar.seminar_csv.order_end_col') => [
                'id' => 'order_end',
                'description' => 'admin.seminar.seminar_csv.order_end_description',
                'required' => false,
            ],

            trans('admin.seminar.seminar_csv.order_count_col') => [
                'id' => 'order_count',
                'description' => 'admin.seminar.seminar_csv.order_count_description',
                'required' => false,
            ],

            trans('admin.seminar.seminar_csv.memo_col') => [
                'id' => 'memo',
                'description' => 'admin.seminar.seminar_csv.memo_description',
                'required' => false,
            ],

            trans('admin.seminar.seminar_csv.create_date_col') => [
                'id' => 'create_date',
                'description' => 'admin.seminar.seminar_csv.create_date_description',
                'required' => false,
            ],

            trans('admin.seminar.seminar_csv.update_date_col') => [
                'id' => 'update_date',
                'description' => 'admin.seminar.seminar_csv.update_date_description',
                'required' => false,
            ],
        ];
    }

    /**
     * 商品登録CSVヘッダー定義
     *
     * @return array
     */
    protected function getProductCsvHeader()
    {
        return [
            trans('admin.product.product_csv.product_id_col') => [
                'id' => 'id',
                'description' => 'admin.product.product_csv.product_id_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.display_status_col') => [
                'id' => 'status',
                'description' => 'admin.product.product_csv.display_status_description',
                'required' => true,
            ],
            trans('admin.product.product_csv.product_name_col') => [
                'id' => 'name',
                'description' => 'admin.product.product_csv.product_name_description',
                'required' => true,
            ],
            trans('admin.product.product_csv.shop_memo_col') => [
                'id' => 'note',
                'description' => 'admin.product.product_csv.shop_memo_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.description_list_col') => [
                'id' => 'description_list',
                'description' => 'admin.product.product_csv.description_list_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.description_detail_col') => [
                'id' => 'description_detail',
                'description' => 'admin.product.product_csv.description_detail_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.keyword_col') => [
                'id' => 'search_word',
                'description' => 'admin.product.product_csv.keyword_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.free_area_col') => [
                'id' => 'free_area',
                'description' => 'admin.product.product_csv.free_area_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.delete_flag_col') => [
                'id' => 'product_del_flg',
                'description' => 'admin.product.product_csv.delete_flag_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.product_image_col') => [
                'id' => 'product_image',
                'description' => 'admin.product.product_csv.product_image_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.category_col') => [
                'id' => 'product_category',
                'description' => 'admin.product.product_csv.category_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.tag_col') => [
                'id' => 'product_tag',
                'description' => 'admin.product.product_csv.tag_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.sale_type_col') => [
                'id' => 'sale_type',
                'description' => 'admin.product.product_csv.sale_type_description',
                'required' => true,
            ],
            trans('admin.product.product_csv.class_category1_col') => [
                'id' => 'class_category1',
                'description' => 'admin.product.product_csv.class_category1_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.class_category2_col') => [
                'id' => 'class_category2',
                'description' => 'admin.product.product_csv.class_category2_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.delivery_duration_col') => [
                'id' => 'delivery_date',
                'description' => 'admin.product.product_csv.delivery_duration_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.product_code_col') => [
                'id' => 'product_code',
                'description' => 'admin.product.product_csv.product_code_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.stock_col') => [
                'id' => 'stock',
                'description' => 'admin.product.product_csv.stock_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.stock_unlimited_col') => [
                'id' => 'stock_unlimited',
                'description' => 'admin.product.product_csv.stock_unlimited_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.sale_limit_col') => [
                'id' => 'sale_limit',
                'description' => 'admin.product.product_csv.sale_limit_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.normal_price_col') => [
                'id' => 'price01',
                'description' => 'admin.product.product_csv.normal_price_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.sale_price_col') => [
                'id' => 'price02',
                'description' => 'admin.product.product_csv.sale_price_description',
                'required' => true,
            ],
            trans('admin.product.product_csv.delivery_fee_col') => [
                'id' => 'delivery_fee',
                'description' => 'admin.product.product_csv.delivery_fee_description',
                'required' => false,
            ],
            trans('admin.product.product_csv.tax_rate_col') => [
                'id' => 'tax_rate',
                'description' => 'admin.product.product_csv.tax_rate_description',
                'required' => false,
            ],
        ];
    }
}
