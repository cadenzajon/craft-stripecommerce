<?php

namespace cadenzajon\stripecommerce\events;

use yii\base\Event;

class OrderCompletedEvent extends Event
{
    /** The Stripe Checkout Session object from the completed webhook. */
    public mixed $session = null;
}
