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

namespace Customize\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\CartItem;
use Eccube\Entity\ProductClass;
use Eccube\Form\DataTransformer\EntityToIdTransformer;
use Eccube\Repository\ProductClassRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContext;
use Eccube\Entity\Master\SaleType;

class AddCartType extends AbstractType
{

    /**
     * @var EccubeConfig
     */
    protected $config;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var \Eccube\Entity\Product
     */
    protected $Product = null;

    /**
     * @var ProductClassRepository
     */
    protected $productClassRepository;

    protected $doctrine;

    public function __construct(ManagerRegistry $doctrine, EccubeConfig $config)
    {
        $this->doctrine = $doctrine;
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /* @var $Product \Eccube\Entity\Product */
        $Product = $options['product'];
        $this->Product = $Product;
        $ProductClasses = $Product->getProductClasses();

        $builder
            ->add('product_id', HiddenType::class, [
                'data' => $Product->getId(),
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(['pattern' => '/^\d+$/']),
                ], ])
            ->add('schedule_id', HiddenType::class, [
                'data' => 0,
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Regex(['pattern' => '/^\d+$/']),
                ], ])
            ->add(
                $builder
                    ->create('ProductClass', HiddenType::class, [
                        'data_class' => null,
                        'data' => $Product->hasProductClass() ? null : $ProductClasses->first(),
                        'constraints' => [
                            new Assert\NotBlank(),
                        ],
                    ])
                    ->addModelTransformer(new EntityToIdTransformer($this->doctrine->getManager(), ProductClass::class))
            );

        if ($Product->getStockFind()) {
            $qtyLabel = $ProductClasses->first() && $ProductClasses->first()->getSaleType() && $ProductClasses->first()->getSaleType()->getId() ? '枚数' : '数量'; // ( Seminar || Product )
            if($ProductClasses->first()->getSaleType()->getId() ==  SaleType::SALE_TYPE_RENTAL){
                $qtyLabel = "数量";
            }
            $builder
                ->add('quantity', IntegerType::class, [
                    'label' => $qtyLabel,
                    'data' => 1,
                    'attr' => [
                        'class' => 'quantity',
                        'min' => 1,
                        'maxlength' => $this->config['eccube_int_len'],
                    ],
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\GreaterThanOrEqual([
                            'value' => 1,
                        ]),
                        new Assert\Regex(['pattern' => '/^\d+$/']),
                    ],
                ]);

            $has_class = $Product->hasProductClass();
            $sale_type = "";
            
            if (!$has_class) {
                $ProductClasses = $Product->getProductClasses();
                foreach ($ProductClasses as $pc) {
                    if ($pc->isVisible()) {
                        $ProductClass = $pc;
                        break;
                    }
                }
                $sale_type = $ProductClass->getSaleType()->getId();
            }

            if($sale_type == constant('Customize\\Entity\\ProductEntity::RENTAL_PRODUCT')){
                $builder
                    ->add('rental_start_date', DateType::class, [
                        'label' => 'レンタル開始日',
                        'required' => false,
                        'input' => 'datetime',
                        'widget' => 'single_text',
                        'format' => 'yyyy-MM-dd',
                        'placeholder' => ['year' => '----', 'month' => '--', 'day' => '--'],
                        'attr' => [
                            'style' => 'min-width: 200px',
                            'class' => 'datetimepicker-input',
                        ],
                        'constraints' => [
                            new Assert\NotBlank(),
                            new Assert\GreaterThanOrEqual([
                                'value' => date('Y-m-d'),
                                'message' => 'form_error.select_is_future_or_now_date',
                            ]),
                        ],
                    ])
                    ->add('rental_min_day', IntegerType::class, [
                        'label' => 'レンタル日数',
                        'data' => $Product->getRentalMin(),
                        'attr' => [
                            'value' => $Product->getRentalMin() ? $Product->getRentalMin() : 1,
                            'class' => 'rental_min_day',
                            'min' => $Product->getRentalMin() ? $Product->getRentalMin() : 1,
                            'maxlength' => $this->config['eccube_int_len'],
                        ],
                        'constraints' => [
                            new Assert\NotBlank(),
                            new Assert\GreaterThanOrEqual([
                                'value' => $Product->getRentalMin() ? $Product->getRentalMin() : 1,
                            ]),
                            new Assert\Regex(['pattern' => '/^\d+$/']),
                        ],
                    ]);
            }


            if ($Product && $Product->getProductClasses()) {
                if (!is_null($Product->getClassName1())) {
                    $builder->add('classcategory_id1', ChoiceType::class, [
                        'label' => $Product->getClassName1(),
                        'choices' => ['common.select' => '__unselected'] + $Product->getClassCategories1AsFlip(),
                        'mapped' => false,
                    ]);
                }
                if (!is_null($Product->getClassName2())) {
                    $builder->add('classcategory_id2', ChoiceType::class, [
                        'label' => $Product->getClassName2(),
                        'choices' => ['common.select' => '__unselected'],
                        'mapped' => false,
                    ]);
                }
            }

            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($Product) {
                $data = $event->getData();
                $form = $event->getForm();
                if (isset($data['classcategory_id1']) && !is_null($Product->getClassName2())) {
                    if ($data['classcategory_id1']) {
                        $form->add('classcategory_id2', ChoiceType::class, [
                            'label' => $Product->getClassName2(),
                            'choices' => ['common.select' => '__unselected'] + $Product->getClassCategories2AsFlip($data['classcategory_id1']),
                            'mapped' => false,
                        ]);
                    }
                }
            });

            $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                /** @var CartItem $CartItem */
                $CartItem = $event->getData();
                $ProductClass = $CartItem->getProductClass();
                // FIXME 価格の設定箇所、ここでいいのか
                if ($ProductClass) {
                    $CartItem
                        ->setProductClass($ProductClass)
                        ->setPrice($ProductClass->getPrice02IncTax());
                }
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('product');
        $resolver->setDefaults([
            'data_class' => CartItem::class,
            'id_add_product_id' => true,
            'constraints' => [
                // FIXME new Assert\Callback(array($this, 'validate')),
            ],
        ]);
    }

    /*
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['id_add_product_id']) {
            foreach ($view->vars['form']->children as $child) {
                $child->vars['id'] .= $options['product']->getId();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'add_cart';
    }

    /**
     * validate
     *
     * @param type $data
     */
    public function validate($data, ExecutionContext $context)
    {
        $context->getValidator()->validate($data['product_class_id'], [
            new Assert\NotBlank(),
        ], '[product_class_id]');
        if ($this->Product->getClassName1()) {
            $context->validateValue($data['classcategory_id1'], [
                new Assert\NotBlank(),
                new Assert\NotEqualTo([
                    'value' => '__unselected',
                    'message' => 'form_error.not_selected',
                ]),
            ], '[classcategory_id1]');
        }
        //商品規格2初期状態(未選択)の場合の返却値は「NULL」で「__unselected」ではない
        if ($this->Product->getClassName2()) {
            $context->getValidator()->validate($data['classcategory_id2'], [
                new Assert\NotBlank(),
                new Assert\NotEqualTo([
                    'value' => '__unselected',
                    'message' => 'form_error.not_selected',
                ]),
            ], '[classcategory_id2]');
        }
    }
}
