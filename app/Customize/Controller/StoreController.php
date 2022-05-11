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


use Customize\Form\Type\SearchSeminarType;
use Customize\Repository\StoreRepository;
use Eccube\Controller\AbstractController;

use Eccube\Entity\BaseInfo;

use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Repository\BaseInfoRepository;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Knp\Component\Pager\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class StoreController extends AbstractController
{

    /**
     * @var BaseInfo
     */
    protected $BaseInfo;

    /**
     * @var AuthenticationUtils
     */
    protected $helper;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

   protected $storeRepository;


    /**
     * ProductController constructor.
     */
    public function __construct(
        BaseInfoRepository $baseInfoRepository,
        StoreRepository $storeRepository,
        AuthenticationUtils $helper,
        AuthorizationCheckerInterface $authorizationChecker
    )
    {
        $this->BaseInfo = $baseInfoRepository->get();
        $this->helper = $helper;
        $this->authorizationChecker = $authorizationChecker;
        $this->storeRepository = $storeRepository;
    }

    /**
     * 商品一覧画面.
     *
     * @Route("/shop/list", name="shop_list")
     * @Template("Shop/list.twig")
     */
    public function index(Request $request, Paginator $paginator)
    {

        // Doctrine SQLFilter
        if ($this->BaseInfo->isOptionNostockHidden()) {
            $this->entityManager->getFilters()->enable('option_nostock_hidden');
        }


        $qb = $this->storeRepository->getQueryBuilderBySearchData();

        $event = new EventArgs(
            [
                'qb' => $qb,
            ],
            $request
        );
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_CUSTOMER_INDEX_SEARCH, $event);

        $pagination = $paginator->paginate(
            $qb
        );

        $data = [
            'pagination' => $pagination,
        ];

//        dump($data);die;

        return $data;
    }

}
