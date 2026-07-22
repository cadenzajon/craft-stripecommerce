<?php

namespace cadenzajon\stripecommerce\models;

use craft\base\Model;

class Settings extends Model
{
    /**
     * Tier definitions, keyed by handle. Options per tier:
     * - default: bool — the tier applied to all visitors
     * - accessCode: string — passcode that activates the tier for a session
     * - userGroup: string — Craft user group handle that activates the tier (Pro)
     *
     * @var array<string, array>
     */
    public array $tiers = [
        'retail' => ['default' => true],
    ];

    /** Stripe price metadata key that names the tier a price belongs to. */
    public string $priceTierMetadataKey = 'tier';

    /**
     * Checkout options:
     * - successUrl: string — site path, may include {CHECKOUT_SESSION_ID}
     * - cancelUrl: string — site path
     * - shippingCountries: string[] — ISO country codes for shipping address collection
     * - allowPromotionCodes: bool
     *
     * @var array<string, mixed>
     */
    public array $checkout = [];

    public function getDefaultTier(): string
    {
        foreach ($this->tiers as $handle => $config) {
            if (!empty($config['default'])) {
                return $handle;
            }
        }

        return array_key_first($this->tiers) ?? 'retail';
    }
}
