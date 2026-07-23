<?php

namespace cadenzajon\stripecart\services;

use cadenzajon\stripecart\events\ResolveTierEvent;
use cadenzajon\stripecart\Plugin;
use Craft;
use craft\elements\User;
use craft\stripe\elements\Price;
use craft\stripe\elements\Product;
use yii\base\Component;

/**
 * Resolves the visitor's pricing tier and the Stripe price a product should
 * sell at within that tier.
 */
class Tiers extends Component
{
    /**
     * Fires after the built-in rules run, letting a site module override the
     * resolved tier (IP allowlist, signed URL, time window, ...).
     */
    public const EVENT_RESOLVE_TIER = 'resolveTier';

    private const SESSION_KEY = 'stripe-cart:tier';

    public function getActiveTier(): string
    {
        $settings = Plugin::getInstance()->getSettings();
        if (!$settings->tiers) {
            return '';
        }
        $tier = null;

        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            $tier = Craft::$app->getSession()->get(self::SESSION_KEY);
            if ($tier === null || !isset($settings->tiers[$tier])) {
                $tier = $this->resolveUserGroupTier();
            }
        }

        $tier ??= $settings->getDefaultTier();

        if ($this->hasEventHandlers(self::EVENT_RESOLVE_TIER)) {
            $event = new ResolveTierEvent(['tier' => $tier]);
            $this->trigger(self::EVENT_RESOLVE_TIER, $event);
            if (isset($settings->tiers[$event->tier])) {
                $tier = $event->tier;
            }
        }

        return $tier;
    }

    /**
     * Activates the tier whose accessCode matches, for the current session.
     * Returns the activated tier handle, or null if no tier matched.
     */
    public function activateByAccessCode(string $code): ?string
    {
        if ($code === '') {
            return null;
        }

        foreach (Plugin::getInstance()->getSettings()->tiers as $handle => $config) {
            $accessCode = (string)($config['accessCode'] ?? '');
            if ($accessCode !== '' && hash_equals($accessCode, $code)) {
                Craft::$app->getSession()->set(self::SESSION_KEY, $handle);
                return $handle;
            }
        }

        return null;
    }

    public function deactivate(): void
    {
        Craft::$app->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * The price a product sells at in the given tier (default: the active tier).
     * Falls back to the default tier's price, then the product's default price.
     */
    public function resolvePrice(Product $product, ?string $tier = null): ?Price
    {
        $settings = Plugin::getInstance()->getSettings();
        if (!$settings->tiers) {
            return $product->getDefaultPrice();
        }
        $tier ??= $this->getActiveTier();
        $key = $settings->priceTierMetadataKey;

        $prices = $product->getPrices();
        $byTier = fn(string $wanted) => $prices->first(
            fn(Price $price) => ($price->getData()['metadata'][$key] ?? null) === $wanted,
        );

        $match = $byTier($tier);

        if (!$match && $tier !== $settings->getDefaultTier()) {
            $match = $byTier($settings->getDefaultTier());
        }

        return $match ?? $product->getDefaultPrice();
    }

    private function resolveUserGroupTier(): ?string
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user instanceof User) {
            return null;
        }

        foreach (Plugin::getInstance()->getSettings()->tiers as $handle => $config) {
            $group = $config['userGroup'] ?? null;
            if ($group && $user->isInGroup($group)) {
                return $handle;
            }
        }

        return null;
    }
}
