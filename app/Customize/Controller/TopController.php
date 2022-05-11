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

use Customize\Repository\SeminarRepository;
use Eccube\Controller\AbstractController;
use Eccube\Repository\ProductRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Routing\Annotation\Route;

class TopController extends AbstractController
{
    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var SeminarRepository
     */
    protected $seminarRepository;

    /**
     * ProductController constructor.
     */
    public function __construct(
        ProductRepository $productRepository,
        SeminarRepository $seminarRepository
    ) {
        $this->productRepository = $productRepository;
        $this->seminarRepository = $seminarRepository;
    }

    /**
     * @Route("/", name="homepage")
     * @Template("index.twig")
     */
    public function index()
    {
//        $Products = $this->productRepository->findAll();
//        $NewSeminar = $this->seminarRepository->findNewSeminar();
//        $ScheduleRecently = $this->seminarRepository->findSeminarByScheduleRecently();

        return [
//            'products' => $Products,
//            'newseminars' => $NewSeminar,
//            'schedules' => $ScheduleRecently,
        ];
    }
}
