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

namespace Plugin\Stripe4\Controller;


use Eccube\Common\EccubeConfig;
use Eccube\Controller\AbstractShoppingController;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Order;
use Eccube\Repository\Master\OrderStatusRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Service\CartService;
use Eccube\Service\MailService;
use Eccube\Service\OrderHelper;
use Eccube\Service\PurchaseFlow\PurchaseContext;
use Plugin\Stripe4\Entity\Config;
use Plugin\Stripe4\Entity\PaymentStatus;
use Plugin\Stripe4\Entity\CreditCard;
use Plugin\Stripe4\Repository\ConfigRepository;
use Plugin\Stripe4\Repository\PaymentStatusRepository;
use Plugin\Stripe4\Repository\CreditCardRepository;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class StripeController
 * @package Plugin\Stripe4\Controller
 *
 * @Route("/shopping")
 */
class PaymentController extends AbstractShoppingController
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var CreditCardRepository
     */
    private $creditCardRepository;

    /**
     * @var OrderStatusRepository
     */
    private $orderStatusRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var PaymentStatusRepository
     */
    private $paymentStatusRepository;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ParameterBag
     */
    private $parameterBag;

    public function __construct(
        CartService $cartService,
        OrderHelper $orderHelper,
        EccubeConfig $eccubeConfig,
        CreditCardRepository $creditCardRepository,
        OrderStatusRepository $orderStatusRepository,
        OrderRepository $orderRepository,
        MailService $mailService,
        PaymentStatusRepository $paymentStatusRepository,
        ConfigRepository $configRepository,
        ParameterBag $parameterBag
    )
    {
        $this->eccubeConfig = $eccubeConfig;
        Stripe::setApiKey($this->eccubeConfig['stripe_secret_key']);

        $this->cartService = $cartService;
        $this->orderHelper = $orderHelper;
        $this->creditCardRepository = $creditCardRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->orderRepository = $orderRepository;
        $this->mailService = $mailService;
        $this->paymentStatusRepository = $paymentStatusRepository;
        $this->config = $configRepository->get();
        $this->parameterBag = $parameterBag;
    }

    /**
     * @Route("/stripe_payment", name="stripe_payment")
     */
    public function payment(Request $request)
    {
        // ?????????????????????
        /** @var Order $Order ????????????????????? */
        $Order = $this->parameterBag->get('stripe.Order');

        if (!$Order) {
            log_info('[Stripe][????????????] ?????????????????????????????????.');

            return $this->redirectToRoute('shopping_error');
        }

        $paymentMethodId = $Order->getStripePaymentMethodId();

        try {
            if (null !== $paymentMethodId) {
                log_info("[Stripe]??????????????????");

                $paymentIntentData = [
                    "amount" => (int)$Order->getPaymentTotal(),
                    "currency" => $this->eccubeConfig["currency"],
                    "payment_method" => $paymentMethodId,
                    "confirmation_method" => "manual",
                    "confirm" => true,
                    "capture_method" => $this->config->getCapture() ? "automatic" : "manual",
                ];

                if($Order->getCustomer())  {
                    if ($Order->getCustomer()->getCreditCards()->count() > 0) {
                        $stripeCustomer = $Order->getCustomer()->getCreditCards()->first()->getStripeCustomerId();
                        $paymentIntentData['customer'] = $stripeCustomer;
                    } else {
                        if ($Order->getStripeSavingCard()) {
                            $stripeCustomer = Customer::create([
                                "email" => $Order->getCustomer()->getEmail()
                            ]);
                            $paymentIntentData['customer'] = $stripeCustomer->id;
                        }
                    }
                }

                log_info("[Stripe]PaymentIntent??????");
                $intent = PaymentIntent::create($paymentIntentData);

                if ($intent->status == "requires_action") {
                    $intent->confirm([
                        'return_url' => $this->generateUrl('stripe_reciever', [], UrlGeneratorInterface::ABSOLUTE_URL)
                    ]);
                }
            } else {
                throw new CardException('[Stripe]????????????????????????????????????????????????????????????');
            }
        } catch (\Exception $e) {
            log_error("[Stripe]" . $e->getMessage());

            $this->rollbackOrder($Order);

            $this->addError($e->getMessage());
            return $this->redirectToRoute('shopping_error');
        }

        return $this->generateResponse($intent, $Order);
    }

    /**
     * @param Request $request
     *
     * @Route("/stripe_reciever", name="stripe_reciever")
     */
    public function reciever(Request $request)
    {
        try {
            if (null !== $request->query->get('payment_intent')) {
                $intent = PaymentIntent::retrieve($request->query->get('payment_intent'));
                if ($intent->status == "requires_confirmation") {
                    $intent->confirm([
                        'return_url' => $this->generateUrl('stripe_reciever', [], UrlGeneratorInterface::ABSOLUTE_URL)
                    ]);
                }

                $Order = $this->orderRepository->findOneBy([
                    'stripe_payment_method_id' => $intent->payment_method,
                    'OrderStatus' => OrderStatus::PENDING
                ]);

                if (null === $Order) {
                    throw new \Exception("[Stripe]????????????????????????????????????");
                }
            } else {
                throw new CardException('[Stripe]??????????????????');
            }
        } catch (\Exception $e) {
            log_error("[Stripe]" . $e->getMessage());

            $this->addError($e->getMessage());
            return $this->redirectToRoute('shopping_error');
        }

        return $this->generateResponse($intent, $Order);
    }

    public function generateResponse(PaymentIntent $intent, Order $Order)
    {
        switch ($intent->status) {
            case "requires_action":
            case "requires_source_action":
                log_info("[Stripe]3D????????????????????????????????????????????????");
                return $this->redirect($intent->next_action->redirect_to_url->url);
            case "requires_payment_method":
            case "requires_source":
                // Card was not properly authenticated, suggest a new payment method
                log_error('[Stripe]???????????????');

                // ??????????????????????????????????????????
                $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::OUTSTANDING);
                $Order->setStripePaymentStatus($PaymentStatus);

                $this->rollbackOrder($Order);

                $message = "Your card was denied, please provide a new payment method";
                $this->addError($message);
                return $this->redirectToRoute("shopping_error");
            case "succeeded":
            case "requires_capture":
                // ?????????????????????????????????????????????
                $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PAID);
                $Order->setOrderStatus($OrderStatus);
                $Order->setPaymentDate(new \DateTime());

                // PaymentIntent??????
                $Order->setStripePaymentIntentId($intent->id);

                if ($this->config->getCapture()) {
                    // ??????????????????????????????????????????
                    $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::ACTUAL_SALES);
                    $Order->setStripePaymentStatus($PaymentStatus);
                } else {
                    // ??????????????????????????????????????????
                    $PaymentStatus = $this->paymentStatusRepository->find(PaymentStatus::PROVISIONAL_SALES);
                    $Order->setStripePaymentStatus($PaymentStatus);
                }

                // ???????????????????????????????????????????????????
                if ($intent->customer) {
                    $paymentMethod = PaymentMethod::retrieve($intent->payment_method);

                    $creditCard = $this->creditCardRepository->findOneBy([
                        "fingerprint" => $paymentMethod->card->fingerprint
                    ]);

                    // DB???????????????????????????
                    if (null === $creditCard) {
                        log_info("[Stripe]???????????????????????????????????????");
                        $creditCard = new CreditCard();
                        $creditCard
                            ->setCustomer($Order->getCustomer())
                            ->setStripeCustomerId($intent->customer)
                            ->setStripePaymentMethodId($intent->payment_method)
                            ->setFingerprint($paymentMethod->card->fingerprint)
                            ->setBrand($paymentMethod->card->brand)
                            ->setLast4($paymentMethod->card->last4);
                        $this->entityManager->persist($creditCard);
                    }
                }

                // purchaseFlow::commit??????????????????????????????????????????
                $this->purchaseFlow->commit($Order, new PurchaseContext());

                log_info('[Stripe][????????????] ??????????????????????????????.', [$Order->getId()]);
                $this->cartService->clear();

                // ??????ID??????????????????????????????
                $this->session->set(OrderHelper::SESSION_ORDER_ID, $Order->getId());

                // ???????????????
                log_info('[????????????] ???????????????????????????????????????.', [$Order->getId()]);
                $this->mailService->sendOrderMail($Order);
                $this->entityManager->flush();

                log_info('[????????????] ?????????????????????????????????. ????????????????????????????????????.', [$Order->getId()]);

                return $this->redirectToRoute("shopping_complete");
        }
    }


    /**
     * @param Order $Order
     */
    private function rollbackOrder(Order $Order)
    {
        // ????????????????????????????????????????????????
        $OrderStatus = $this->orderStatusRepository->find(OrderStatus::PROCESSING);
        $Order->setOrderStatus($OrderStatus);

        // ????????????????????????
        $this->purchaseFlow->rollback($Order, new PurchaseContext());

        $this->entityManager->flush();
    }

}
