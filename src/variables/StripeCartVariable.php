<?php

namespace cadenzajon\stripecart\variables;

use cadenzajon\stripecart\models\CartItem;
use cadenzajon\stripecart\Plugin;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;

/**
 * Available in Twig as craft.stripeCart.
 */
class StripeCartVariable
{
    /**
     * @return CartItem[]
     */
    public function getItems(): array
    {
        return Plugin::getInstance()->cart->getHydratedItems();
    }

    public function getCount(): int
    {
        return Plugin::getInstance()->cart->getCount();
    }

    public function getIsEmpty(): bool
    {
        return Plugin::getInstance()->cart->getIsEmpty();
    }

    /** The active pricing tier handle for this session. */
    public function getTier(): string
    {
        return Plugin::getInstance()->tiers->getActiveTier();
    }

    /** The price a product sells at in the session's active tier. */
    public function priceFor(Product $product): ?Price
    {
        return Plugin::getInstance()->tiers->resolvePrice($product);
    }
}
