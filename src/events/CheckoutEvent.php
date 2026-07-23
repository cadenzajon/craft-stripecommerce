<?php

namespace cadenzajon\stripecart\events;

use yii\base\Event;

class CheckoutEvent extends Event
{
    /** @var array<array{price: string, quantity: int}> */
    public array $lineItems = [];

    /** Additional Stripe Checkout Session params. */
    public array $params = [];

    public string $successUrl = '';
    public string $cancelUrl = '';
}
