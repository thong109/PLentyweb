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

namespace Customize\Controller\Admin\System;

use Customize\Entity\StoreMember;
use Customize\Repository\StoreRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Member;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Admin\MemberType;
use Eccube\Repository\MemberRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class StoreMemberController extends AbstractController
{
    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var MemberRepository
     */
    protected $memberRepository;

    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    /**
     * @var StoreRepository
     */
    protected $storeRepository;

    /**
     * MemberController constructor.
     */
    public function __construct(
        EncoderFactoryInterface $encoderFactory,
        MemberRepository $memberRepository,
        TokenStorageInterface $tokenStorage,
        StoreRepository $storeRepository
    ) {
        $this->encoderFactory = $encoderFactory;
        $this->memberRepository = $memberRepository;
        $this->tokenStorage = $tokenStorage;
        $this->storeRepository = $storeRepository;
    }

    /**
     * @Route("/%eccube_admin_route%/setting/system/member/new/{IdStore}", name="admin_setting_system_store_member_new")
     * @Template("@admin/Setting/System/member_edit.twig")
     */
    public function createStoreMember(Request $request, $IdStore)
    {
        $LoginMember = clone $this->tokenStorage->getToken()->getUser();
        $this->entityManager->detach($LoginMember);

        $Member = new Member();
        $builder = $this->formFactory
            ->createBuilder(MemberType::class, $Member);

        $event = new EventArgs([
            'builder' => $builder,
            'Member' => $Member,
        ], $request);
        $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_SETTING_SYSTEM_MEMBER_EDIT_INITIALIZE, $event);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $encoder = $this->encoderFactory->getEncoder($Member);
            $salt = $encoder->createSalt();
            $rawPassword = $Member->getPassword();
            $encodedPassword = $encoder->encodePassword($rawPassword, $salt);
            $Member
                ->setSalt($salt)
                ->setPassword($encodedPassword);

            $this->memberRepository->save($Member);

            $event = new EventArgs(
                [
                    'form' => $form,
                    'Member' => $Member,
                ],
                $request
            );
            $this->eventDispatcher->dispatch(EccubeEvents::ADMIN_SETTING_SYSTEM_MEMBER_EDIT_COMPLETE, $event);

            $Store = $this->storeRepository->find($IdStore);


            $storeMember = new StoreMember();
            // add images
            $storeMember->setMember($Member);
            $storeMember->setStore($Store);

            $this->entityManager->persist($storeMember);
            $this->entityManager->flush();


            $this->addSuccess('admin.common.save_complete', 'admin');

            return $this->redirectToRoute('admin_setting_system_member_edit', ['id' => $Member->getId()]);
        }

        $this->tokenStorage->getToken()->setUser($LoginMember);

        return [
            'form' => $form->createView(),
            'Member' => $Member,
        ];
    }
}
