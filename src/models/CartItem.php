<?php

namespace cadenzajon\stripecart\models;

use craft\stripe\elements\Price;
use craft\stripe\elements\Product;

/**
 * A hydrated cart row: the synced product element, the tier-resolved price
 * element, and the quantity.
 */
class CartItem
{
    public function __construct(
        public Product $product,
        public Price $price,
        public int $qty,
    ) {
    }
}
