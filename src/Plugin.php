<?php

namespace cadenzajon\stripecommerce;

use cadenzajon\stripecommerce\events\OrderCompletedEvent;
use cadenzajon\stripecommerce\models\Settings;
use cadenzajon\stripecommerce\services\Cart;
use cadenzajon\stripecommerce\services\Checkout;
use cadenzajon\stripecommerce\services\Tiers;
use cadenzajon\stripecommerce\variables\StripeCartVariable;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\stripe\events\StripeEvent;
use craft\stripe\services\Webhooks as StripeWebhooks;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

/**
 * @property-read Cart $cart
 * @property-read Tiers $tiers
 * @property-read Checkout $checkout
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'cart' => Cart::class,
                'tiers' => Tiers::class,
                'checkout' => Checkout::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'cadenzajon\\stripecommerce\\console\\controllers';
        } else {
            $this->controllerNamespace = 'cadenzajon\\stripecommerce\\controllers';
        }

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('stripeCart', StripeCartVariable::class);
        });

        // The official plugin receives all webhooks; re-fire completed checkouts
        // as this plugin's orderCompleted event.
        Event::on(StripeWebhooks::class, StripeWebhooks::EVENT_STRIPE_EVENT, function(StripeEvent $event) {
            if ($event->stripeEvent->type === 'checkout.session.completed') {
                $this->checkout->trigger(Checkout::EVENT_ORDER_COMPLETED, new OrderCompletedEvent([
                    'session' => $event->stripeEvent->data->object,
                ]));
            }
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}
