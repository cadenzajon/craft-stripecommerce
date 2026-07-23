<?php

namespace cadenzajon\stripecart\services;

use cadenzajon\stripecart\models\CartItem;
use cadenzajon\stripecart\Plugin;
use Craft;
use craft\stripe\elements\Product;
use yii\base\Component;

/**
 * Session cart. The browser only ever supplies product IDs and quantities;
 * prices are resolved server-side from the visitor's tier.
 */
class Cart extends Component
{
    private const SESSION_KEY = 'stripe-cart:cart';

    /**
     * @return array<int, int> productId => qty
     */
    public function getItems(): array
    {
        return Craft::$app->getSession()->get(self::SESSION_KEY, []);
    }

    public function add(int $productId, int $qty = 1): void
    {
        $items = $this->getItems();
        $items[$productId] = ($items[$productId] ?? 0) + max(1, $qty);
        $this->setItems($items);
    }

    public function update(int $productId, int $qty): void
    {
        $items = $this->getItems();
        if ($qty <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $qty;
        }
        $this->setItems($items);
    }

    public function remove(int $productId): void
    {
        $this->update($productId, 0);
    }

    public function clear(): void
    {
        Craft::$app->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * @return CartItem[]
     */
    public function getHydratedItems(): array
    {
        $tiers = Plugin::getInstance()->tiers;
        $items = [];

        foreach ($this->getItems() as $productId => $qty) {
            $product = Product::find()->id($productId)->one();
            if (!$product) {
                continue;
            }
            $price = $tiers->resolvePrice($product);
            if (!$price) {
                continue;
            }
            $items[] = new CartItem($product, $price, $qty);
        }

        return $items;
    }

    public function getCount(): int
    {
        return array_sum($this->getItems());
    }

    public function getIsEmpty(): bool
    {
        return $this->getItems() === [];
    }

    /**
     * Stripe Checkout line items with tier-resolved price IDs.
     *
     * @return array<array{price: string, quantity: int}>
     */
    public function getLineItems(): array
    {
        return array_map(
            fn(CartItem $item) => ['price' => $item->price->stripeId, 'quantity' => $item->qty],
            $this->getHydratedItems(),
        );
    }

    private function setItems(array $items): void
    {
        Craft::$app->getSession()->set(self::SESSION_KEY, $items);
    }
}
