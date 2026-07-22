<?php

namespace cadenzajon\stripecommerce\controllers;

use cadenzajon\stripecommerce\Plugin;
use craft\web\Controller;
use yii\web\Response;

class CheckoutController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    /**
     * Sends the visitor to Stripe Checkout for the current cart.
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();

        try {
            $url = Plugin::getInstance()->checkout->getCheckoutUrl();
        } catch (\RuntimeException $e) {
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure($e->getMessage());
            }
            $this->setFailFlash($e->getMessage());

            return $this->redirectToPostedUrl();
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['redirect' => $url]);
        }

        return $this->redirect($url);
    }

    /**
     * Return landing after Stripe Checkout: clears the cart and renders the
     * site's checkout/success template.
     */
    public function actionSuccess(): Response
    {
        Plugin::getInstance()->cart->clear();

        return $this->renderTemplate('checkout/success', [
            'sessionId' => $this->request->getQueryParam('session_id'),
        ]);
    }
}
