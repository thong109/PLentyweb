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

namespace Eccube\Service;

use Customize\Form\Type\Admin\SearchSeminarType;
use Customize\Form\Type\Admin\SearchStoreType;
use Customize\Repository\SeminarRepository;
use Customize\Repository\StoreRepository;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Csv;
use Eccube\Entity\Master\CsvType;
use Eccube\Form\Type\Admin\SearchCustomerType;
use Eccube\Form\Type\Admin\SearchOrderType;
use Customize\Form\Type\Admin\SearchProductType;
use Eccube\Form\Type\Admin\StoreType;
use Eccube\Repository\CsvRepository;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\Master\CsvTypeRepository;
use Eccube\Repository\OrderRepository;
use Customize\Repository\ProductRepository;
use Eccube\Repository\ShippingRepository;
use Eccube\Util\EntityUtil;
use Eccube\Util\FormUtil;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class CsvExportService
{
    /**
     * @var resource
     */
    protected $fp;

    /**
     * @var boolean
     */
    protected $closed = false;

    /**
     * @var \Closure
     */
    protected $convertEncodingCallBack;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var QueryBuilder;
     */
    protected $qb;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var CsvType
     */
    protected $CsvType;

    /**
     * @var Csv[]
     */
    protected $Csvs;

    /**
     * @var CsvRepository
     */
    protected $csvRepository;

    /**
     * @var CsvTypeRepository
     */
    protected $csvTypeRepository;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var ShippingRepository
     */
    protected $shippingRepository;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var SeminarRepository
     */
    protected $seminarRepository;

    private $storeRepository;

    /**
     * CsvExportService constructor.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CsvRepository $csvRepository,
        CsvTypeRepository $csvTypeRepository,
        OrderRepository $orderRepository,
        ShippingRepository $shippingRepository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        EccubeConfig $eccubeConfig,
        FormFactoryInterface $formFactory,
        SeminarRepository $seminarRepository,
        StoreRepository $storeRepository
    ) {
        $this->entityManager = $entityManager;
        $this->csvRepository = $csvRepository;
        $this->csvTypeRepository = $csvTypeRepository;
        $this->orderRepository = $orderRepository;
        $this->shippingRepository = $shippingRepository;
        $this->customerRepository = $customerRepository;
        $this->eccubeConfig = $eccubeConfig;
        $this->productRepository = $productRepository;
        $this->formFactory = $formFactory;
        $this->seminarRepository = $seminarRepository;
        $this->storeRepository = $storeRepository;
    }

    /**
     * @param $config
     */
    public function setConfig($config)
    {
        $this->eccubeConfig = $config;
    }

    public function setCsvRepository(CsvRepository $csvRepository)
    {
        $this->csvRepository = $csvRepository;
    }

    public function setCsvTypeRepository(CsvTypeRepository $csvTypeRepository)
    {
        $this->csvTypeRepository = $csvTypeRepository;
    }

    public function setOrderRepository(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function setCustomerRepository(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function setProductRepository(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function setEntityManager(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    public function setExportQueryBuilder(QueryBuilder $qb)
    {
        $this->qb = $qb;
    }

    /**
     * Csv????????????Service?????????????????????.
     *
     * @param $CsvType|integer
     */
    public function initCsvType($CsvType)
    {
        if ($CsvType instanceof CsvType) {
            $this->CsvType = $CsvType;
        } else {
            $this->CsvType = $this->csvTypeRepository->find($CsvType);
        }

        $criteria = [
            'CsvType' => $CsvType,
            'enabled' => true,
        ];
        $orderBy = [
            'sort_no' => 'ASC',
        ];

        $this->Csvs = $this->csvRepository->findBy($criteria, $orderBy);
    }

    /**
     * @return Csv[]
     */
    public function getCsvs()
    {
        return $this->Csvs;
    }

    /**
     * ???????????????????????????.
     * ????????????????????????????????????, ?????????initCsvType($CsvType)???????????????????????????????????????.
     */
    public function exportHeader()
    {
        if (is_null($this->CsvType) || is_null($this->Csvs)) {
            throw new \LogicException('init csv type incomplete.');
        }

        $row = [];
        foreach ($this->Csvs as $Csv) {
            $row[] = $Csv->getDispName();
        }

        $this->fopen();
        $this->fputcsv($row);
        $this->fclose();
    }

    /**
     * ???????????????????????????????????????????????????????????????.
     * ????????????????????????????????????, ?????????setExportQueryBuilder($qb)????????????????????????????????????????????????????????????????????????.
     */
    public function exportData(\Closure $closure)
    {
        if (is_null($this->qb) || is_null($this->entityManager)) {
            throw new \LogicException('query builder not set.');
        }

        $this->fopen();

        $query = $this->qb->getQuery();
        foreach ($query->getResult() as $iterableResult) {
            $closure($iterableResult, $this);
            $this->entityManager->detach($iterableResult);
            $query->free();
            flush();
        }

        $this->fclose();
    }

    /**
     * CSV????????????????????????, ??????????????????????????????.
     *
     * @param $entity
     *
     * @return string|null
     */
    public function getData(Csv $Csv, $entity)
    {
        // ????????????????????????????????????????????????????????????.
        $csvEntityName = str_replace('\\\\', '\\', $Csv->getEntityName());
        $entityName = ClassUtils::getClass($entity);
        if ($csvEntityName !== $entityName) {
            return null;
        }

        // ???????????????????????????????????????????????????????????????????????????.
        if (!$entity->offsetExists($Csv->getFieldName())) {
            return null;
        }

        // ??????????????????.
        $data = $entity->offsetGet($Csv->getFieldName());

        // one to one ????????????, dtb_csv.reference_field_name, ?????????????????????????????????.
        if ($data instanceof \Eccube\Entity\AbstractEntity) {
            if (EntityUtil::isNotEmpty($data)) {
                return $data->offsetGet($Csv->getReferenceFieldName());
            }
        } elseif ($data instanceof \Doctrine\Common\Collections\Collection) {
            // one to many????????????, ?????????????????????????????????.
            $array = [];
            foreach ($data as $elem) {
                if (EntityUtil::isNotEmpty($elem)) {
                    $array[] = $elem->offsetGet($Csv->getReferenceFieldName());
                }
            }

            return implode($this->eccubeConfig['eccube_csv_export_multidata_separator'], $array);
        } elseif ($data instanceof \DateTime) {
            // datetime????????????????????????????????????.
            return $data->format($this->eccubeConfig['eccube_csv_export_date_format']);
        } else {
            // ????????????????????????????????????.
            return $data;
        }

        return null;
    }

    /**
     * ?????????????????????????????????????????????????????????????????????????????????.
     *
     * @return \Closure
     */
    public function getConvertEncodingCallback()
    {
        $config = $this->eccubeConfig;

        return function ($value) use ($config) {
            return mb_convert_encoding(
                (string) $value, $config['eccube_csv_export_encoding'], 'UTF-8'
            );
        };
    }

    public function fopen()
    {
        if (is_null($this->fp) || $this->closed) {
            $this->fp = fopen('php://output', 'w');
        }
    }

    /**
     * @param $row
     */
    public function fputcsv($row)
    {
        if (is_null($this->convertEncodingCallBack)) {
            $this->convertEncodingCallBack = $this->getConvertEncodingCallback();
        }

        fputcsv($this->fp, array_map($this->convertEncodingCallBack, $row), $this->eccubeConfig['eccube_csv_export_separator']);
    }

    public function fclose()
    {
        if (!$this->closed) {
            fclose($this->fp);
            $this->closed = true;
        }
    }

    /**
     * ?????????????????????????????????????????????.
     *
     * @return QueryBuilder
     */
    public function getOrderQueryBuilder(Request $request)
    {
        $session = $request->getSession();
        $builder = $this->formFactory
            ->createBuilder(SearchOrderType::class);
        $searchForm = $builder->getForm();

        $viewData = $session->get('eccube.admin.order.search', []);
        $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

        // ?????????????????????????????????????????????.
        $qb = $this->orderRepository
            ->getQueryBuilderBySearchDataForAdmin($searchData);

        return $qb;
    }

    /**
     * ?????????????????????????????????????????????.
     *
     * @return QueryBuilder
     */
    public function getCustomerQueryBuilder(Request $request)
    {
        $session = $request->getSession();
        $builder = $this->formFactory
            ->createBuilder(SearchCustomerType::class);
        $searchForm = $builder->getForm();

        $viewData = $session->get('eccube.admin.customer.search', []);
        $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

        // ?????????????????????????????????????????????.
        $qb = $this->customerRepository
            ->getQueryBuilderBySearchData($searchData);

        return $qb;
    }

    /**
     * ?????????????????????????????????????????????.
     *
     * @return QueryBuilder
     */
    public function getProductQueryBuilder(Request $request)
    {
        $session = $request->getSession();
        $builder = $this->formFactory
            ->createBuilder(SearchProductType::class);
        $searchForm = $builder->getForm();

        $viewData = $session->get('eccube.admin.product.search', []);
        $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

        // ?????????????????????????????????????????????.
        $qb = $this->productRepository
            ->getQueryBuilderBySearchDataForAdmin($searchData);

        return $qb;
    }

    /**
     * ?????????????????????????????????????????????.
     *
     * @return QueryBuilder
     */
    public function getShopQueryBuilder(Request $request)
    {
        $session = $request->getSession();
        $builder = $this->formFactory
            ->createBuilder(SearchStoreType::class);
        $searchForm = $builder->getForm();

        $viewData = $session->get('eccube.admin.customer.search', []);
        $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

        // ?????????????????????????????????????????????.
        $qb = $this->storeRepository
            ->getQueryBuilderBySearchData();

        return $qb;
    }

    /**
     * ?????????????????????????????????????????????.
     *
     * @return QueryBuilder
     */
    public function getSeminarQueryBuilder(Request $request)
    {
        $session = $request->getSession();
        $builder = $this->formFactory
            ->createBuilder(SearchSeminarType::class);
        $searchForm = $builder->getForm();

        $viewData = $session->get('eccube.admin.product.search', []);
        $searchData = FormUtil::submitAndGetData($searchForm, $viewData);

        // ?????????????????????????????????????????????.
        $qb = $this->seminarRepository
            ->getQueryBuilderBySearchDataForAdmin($searchData);

        return $qb;
    }
}
