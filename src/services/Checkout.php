<?php

namespace cadenzajon\stripecommerce\services;

use cadenzajon\stripecommerce\events\CheckoutEvent;
use cadenzajon\stripecommerce\Plugin;
use craft\helpers\UrlHelper;
use craft\stripe\Plugin as StripePlugin;
use yii\base\Component;

/**
 * Converts the session cart into a Stripe Checkout Session via the official
 * plugin's checkout service.
 */
class Checkout extends Component
{
    /**
     * Fires before the cart is handed to the official plugin's checkout,
     * exposing line items and session params for modification.
     */
    public const EVENT_BEFORE_CHECKOUT = 'beforeCheckout';

    /**
     * Fires when a checkout.session.completed webhook arrives, with the
     * Checkout Session payload.
     */
    public const EVENT_ORDER_COMPLETED = 'orderCompleted';

    /**
     * Returns the Stripe-hosted Checkout URL for the current cart.
     */
    public function getCheckoutUrl(): string
    {
        $lineItems = Plugin::getInstance()->cart->getLineItems();
        if (!$lineItems) {
            throw new \RuntimeException('The cart is empty.');
        }

        $settings = Plugin::getInstance()->getSettings()->checkout;

        $params = [];
        if (!empty($settings['shippingCountries'])) {
            $params['shipping_address_collection'] = ['allowed_countries' => $settings['shippingCountries']];
        }
        if (!empty($settings['allowPromotionCodes'])) {
            $params['allow_promotion_codes'] = true;
        }
        if (!empty($settings['shippingOptions'])) {
            $params['shipping_options'] = array_map(
                fn(string $rateId) => ['shipping_rate' => $rateId],
                $settings['shippingOptions'],
            );
        }

        $event = new CheckoutEvent([
            'lineItems' => $lineItems,
            'params' => $params,
            'successUrl' => $this->siteUrl($settings['successUrl'] ?? 'checkout/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancelUrl' => $this->siteUrl($settings['cancelUrl'] ?? 'cart'),
        ]);
        $this->trigger(self::EVENT_BEFORE_CHECKOUT, $event);

        return StripePlugin::getInstance()->getCheckout()->getCheckoutUrl(
            $event->lineItems,
            null,
            $event->successUrl,
            $event->cancelUrl,
            $event->params ?: null,
        );
    }

    /**
     * Builds an absolute site URL without URL-encoding a {CHECKOUT_SESSION_ID}
     * placeholder in the query string.
     */
    private function siteUrl(string $url): string
    {
        if (UrlHelper::isAbsoluteUrl($url)) {
            return $url;
        }

        [$path, $query] = array_pad(explode('?', $url, 2), 2, null);
        $absolute = UrlHelper::siteUrl($path);

        if ($query === null) {
            return $absolute;
        }

        return $absolute . (str_contains($absolute, '?') ? '&' : '?') . $query;
    }
}
