<?php

namespace cadenzajon\stripecart\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use craft\stripe\elements\Product;
use craft\stripe\Plugin as StripePlugin;
use yii\console\ExitCode;

/**
 * Syncs the Stripe catalog into Craft.
 */
class SyncController extends Controller
{
    /**
     * Pulls all products and prices from Stripe (a convenience wrapper around
     * the official plugin's sync). Safe to re-run any time.
     */
    public function actionIndex(): int
    {
        $stripe = StripePlugin::getInstance();

        $this->stdout("Syncing products from Stripe...\n");
        $stripe->getProducts()->syncAllProducts();

        $this->stdout("Syncing prices from Stripe...\n");
        $stripe->getPrices()->syncAllPrices();

        $count = Product::find()->status(null)->count();
        $this->stdout("Done. {$count} products in Craft.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }
}
