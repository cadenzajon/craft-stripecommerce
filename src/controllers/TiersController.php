<?php

namespace cadenzajon\stripecommerce\controllers;

use cadenzajon\stripecommerce\Plugin;
use craft\web\Controller;
use yii\web\Response;

class TiersController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    /**
     * Activates a pricing tier for this session by access code.
     */
    public function actionActivate(): Response
    {
        $this->requirePostRequest();
        $code = (string)$this->request->getRequiredBodyParam('accessCode');

        $tier = Plugin::getInstance()->tiers->activateByAccessCode($code);

        if ($tier === null) {
            if ($this->request->getAcceptsJson()) {
                return $this->asFailure('Invalid access code.');
            }
            $this->setFailFlash('Invalid access code.');

            return $this->redirectToPostedUrl();
        }

        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess(data: ['tier' => $tier]);
        }
        $this->setSuccessFlash('Pricing unlocked.');

        return $this->redirectToPostedUrl();
    }

    /**
     * Reverts this session to the default tier.
     */
    public function actionDeactivate(): Response
    {
        $this->requirePostRequest();
        Plugin::getInstance()->tiers->deactivate();

        if ($this->request->getAcceptsJson()) {
            return $this->asSuccess();
        }

        return $this->redirectToPostedUrl();
    }
}
