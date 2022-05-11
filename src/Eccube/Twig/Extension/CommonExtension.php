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

namespace Eccube\Twig\Extension;

use Customize\Entity\ProductEntity;
use Customize\Repository\StoreRepository;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\SaleType;
use Eccube\Repository\Master\SaleTypeRepository;
use Customize\Repository\ProductRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CommonExtension extends AbstractExtension
{
    const TOP_PAGE_LIMIT = 4;
    /**
     * @var $storeRepository;
     * @var $saleTypeRepository
     * @var $productRepository
     */
    private $eccubeConfig;
    private $storeRepository;
    private $saleTypeRepository;
    private $productRepository;

    /**
     * TaxExtension constructor.
     */
    public function __construct(
        EccubeConfig $eccubeConfig,
        StoreRepository $storeRepository,
        SaleTypeRepository $saleTypeRepository,
        ProductRepository $productRepository
    )
    {
        $this->eccubeConfig = $eccubeConfig;
        $this->storeRepository = $storeRepository;
        $this->saleTypeRepository = $saleTypeRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return TwigFunction[] An array of functions
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('getTopSaleTypes', [$this, 'getTopSaleTypes']),
            new TwigFunction('getTopProducts', [$this, 'getTopProducts']),
            new TwigFunction('getTopStores', [$this, 'getTopStores']),
            new TwigFunction('getTopECLink', [$this, 'getTopECLink']),
        ];
    }

    /**
     * @author vungpv93@gmail.com
     * @param null $saleType
     * @return array
     */
    public function getTopProducts($saleTypeId = null, $highlight = null, $mode = '')
    {
//        $data = $this->productRepository->findBy([], ['id' => 'DESC'], self::TOP_PAGE_LIMIT);
        $data = $this->productRepository->getTopPage($saleTypeId, $highlight, $mode);

        return $data;
    }

    public function getTopECLink()
    {
        $qb = $this->productRepository->createQueryBuilder('p');
        $qb->select('p')
//            ->addSelect(['pc', 'cc1', 'cc2', 'pi', 'pt', 'tr', 'ps'])
            ->innerJoin('p.ProductClasses', 'pc')
            // XXX Joined 'TaxRule' and 'ProductStock' to prevent lazy loading
            ->leftJoin('pc.TaxRule', 'tr')
            ->leftJoin('pc.ProductStock', 'ps')
//            ->innerJoin('pc.ProductStock', 'ps')
            ->leftJoin('pc.ClassCategory1', 'cc1')
            ->leftJoin('pc.ClassCategory2', 'cc2')
            ->leftJoin('p.ProductImage', 'pi')
            ->leftJoin('p.ProductTag', 'pt')
            ->andWhere('pc.visible = :visible')
            ->andWhere('p.Status = :display')
            ->setParameter('display', 1)
            ->setParameter('visible', true);

        $qb
            ->andWhere('pc.SaleType = :SaleType')
            ->setParameter('SaleType', ProductEntity::ECLINK_PRODUCT);

        $qb->groupBy('p.id');

        $qb->orderBy('p.create_date', 'DESC');
        $qb->addOrderBy('p.id', 'DESC');

        $qb->setMaxResults(4);

        $products = $qb
        ->getQuery()
        ->useResultCache(true, $this->eccubeConfig['eccube_result_cache_lifetime_short'])
        ->getResult();

//        dump($products);die;

        return $products;
    }

    /**
     * @author vungpv93@gmail.com
     * @return array
     */
    public function getTopSaleTypes()
    {
        $saleTypeIdList = [
            SaleType::SALE_TYPE_RENTAL,
            SaleType::SALE_TYPE_VIDEO,
            SaleType::SALE_TYPE_EC_LINK,
            SaleType::SALE_TYPE_SEMINAR,
        ];
        $data = $this->saleTypeRepository->findBy(['id' => $saleTypeIdList]);

        return $data;
    }

    /**
     * @author vungpv93@gmail.com
     * @return array
     */
    public function getTopStores()
    {
        $data = $this->storeRepository->findBy([], ['id' => 'DESC']);

//        dump($data);die;
        return $data;
    }
}
