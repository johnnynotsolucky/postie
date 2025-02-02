<?php
namespace verbb\postie\helpers;

use Craft;
use craft\elements\Address;
use craft\helpers\ArrayHelper;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;

class PostieHelper
{
    // Static Methods
    // =========================================================================

    public static function getSignature(Order $order, string $prefix = ''): string
    {
        $totalLength = 0;
        $totalWidth = 0;
        $totalHeight = 0;

        foreach (self::getOrderLineItems($order) as $lineItem) {
            $totalLength += ($lineItem->qty * $lineItem->length);
            $totalWidth += ($lineItem->qty * $lineItem->width);
            $totalHeight += ($lineItem->qty * $lineItem->height);
        }

        $signature = implode('.', [
            $prefix,
            $order->getTotalQty(),
            $order->getTotalWeight(),
            $totalWidth,
            $totalHeight,
            $totalLength,
            implode('.', self::getAddressLines($order->shippingAddress)),
        ]);

        return md5($signature);
    }

    public static function getAddressLines(Address $address = null): array
    {
        if (!$address) {
            return [];
        }

        $addressLines = [
            'countryCode' => $address->countryCode,
            'administrativeArea' => $address->administrativeArea,
            'locality' => $address->locality,
            'dependentLocality' => $address->dependentLocality,
            'postalCode' => $address->postalCode,
            'sortingCode' => $address->sortingCode,
            'addressLine1' => $address->addressLine1,
            'addressLine2' => $address->addressLine2,
            'organization' => $address->organization,
            'organizationTaxId' => $address->organizationTaxId,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'title' => $address->title,
            'fullName' => $address->fullName,
            'status' => $address->status,
        ];

        // Remove blank lines
        $addressLines = array_filter($addressLines);

        array_walk($addressLines, function(&$value) {
            $value = Craft::$app->getFormatter()->asText($value);
        });

        return $addressLines;
    }

    public static function getOrderLineItems(Order $order): array
    {
        $items = [];
        $discounts = Commerce::getInstance()->getDiscounts()->getAllActiveDiscounts($order);
        $hasLineItemLevelShippingRelatedDiscounts = (bool)ArrayHelper::firstWhere($discounts, 'hasFreeShippingForMatchingItems', true, false);

        foreach ($order->getLineItems() as $item) {
            $hasFreeShippingFromDiscount = false;

            if ($hasLineItemLevelShippingRelatedDiscounts) {
                foreach ($discounts as $discount) {
                    $matchedLineItem = Commerce::getInstance()->getDiscounts()->matchLineItem($item, $discount, true);
                    
                    if ($discount->hasFreeShippingForMatchingItems && $matchedLineItem) {
                        $hasFreeShippingFromDiscount = true;
                        break;
                    }

                    if ($matchedLineItem && $discount->stopProcessing) {
                        break;
                    }
                }
            }

            $freeShippingFlagOnProduct = $item->purchasable->hasFreeShipping();
            $shippable = Commerce::getInstance()->getPurchasables()->isPurchasableShippable($item->getPurchasable());
            
            if (!$freeShippingFlagOnProduct && !$hasFreeShippingFromDiscount && $shippable) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
