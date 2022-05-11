<?php
/**
 * This file is part of Stripe4
 *
 * Copyright(c) Akira Kurozumi <info@a-zumi.net>
 *
 * https://a-zumi.net
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Stripe4\Form\Extension;


use Eccube\Entity\Order;
use Eccube\Form\Type\Shopping\OrderType;
use Eccube\Form\Type\ToggleSwitchType;
use Plugin\Stripe4\Form\Type\CreditCardType;
use Plugin\Stripe4\Service\Method\CreditCard;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class CreditCardExtension extends AbstractTypeExtension
{
    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(
        SessionInterface $session
    )
    {
        $this->session = $session;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['skip_add_form']) {
            return;
        }

        $builder
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
                /** @var FormInterface $form */
                $form = $event->getForm();
                /** @var Order $order */
                $order = $event->getData();

                if ($order->getPayment()->getMethodClass() === CreditCard::class) {
                    $form
                        ->add('stripe_payment_method_id', HiddenType::class);

                    if ($Customer = $order->getCustomer()) {
                        $form
                            ->add('stripe_saving_card', ToggleSwitchType::class, [
                                'mapped' => true,
                                'label' => 'カード情報を保存する'
                            ])
                            ->add('cards', CreditCardType::class, [
                                'mapped' => false,
                                'expanded' => true,
                                'choices' => $order->getCustomer()->getCreditCards()
                            ]);
                    }
                }
            });

        $builder
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
                /** @var FormInterface $form */
                $form = $event->getForm();
                /** @var Order $order */
                $order = $event->getData();

                if ($order->getPayment()->getMethodClass() === CreditCard::class) {
                    if($form->has('stripe_payment_method_id')) {
                        if (null === $order->getStripePaymentMethodId()) {
                            $form->get('stripe_payment_method_id')->addError(new FormError(trans('エラーが発生しました')));
                        }
                    }
                }
            });
    }

    /**
     * @inheritDoc
     */
    public function getExtendedType()
    {
        // TODO: Implement getExtendedType() method.
        return OrderType::class;
    }

    /**
     * @return iterable
     */
    public function getExtendedTypes(): iterable
    {
        return [OrderType::class];
    }
}
