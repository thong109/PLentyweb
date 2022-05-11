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

namespace Eccube\Service\PurchaseFlow\Processor;

use Eccube\Entity\ItemInterface;
use Eccube\Entity\OrderItem;
use Eccube\Service\PurchaseFlow\ItemValidator;
use Eccube\Service\PurchaseFlow\PurchaseContext;

/**
 * 販売価格の変更検知.
 */
class PriceChangeValidator extends ItemValidator
{
    /**
     * @throws \Eccube\Service\PurchaseFlow\InvalidItemException
     */
    public function validate(ItemInterface $item, PurchaseContext $context)
    {
        if (!$item->isProduct()) {
            return;
        }

        if ($item instanceof OrderItem){
            if((int)$item->getRentalMinDay() != 0){
                $price = $item->getPrice()/$item->getRentalMinDay();
            }else {
                $price = $item->getPrice();
            }
            $realPrice = $item->getProductClass()->getPrice02();
        } else {
            // CartItem::priceは税込金額.
            if((int)$item->getRentalMinDay() != 0){
                $price = $item->getPrice()/$item->getRentalMinDay();
            }else {
                $price = $item->getPrice();
            }
            $realPrice = $item->getProductClass()->getPrice02IncTax();
        }

        if ($price != $realPrice) {
            $this->throwInvalidItemException('front.shopping.price_changed', $item->getProductClass());
        }
    }
}
