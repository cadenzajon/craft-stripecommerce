<?php

namespace cadenzajon\stripecommerce\events;

use yii\base\Event;

class ResolveTierEvent extends Event
{
    /** The resolved tier handle. Handlers may set a different configured tier. */
    public string $tier;
}
