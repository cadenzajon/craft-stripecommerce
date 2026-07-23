<?php

namespace cadenzajon\stripecart\controllers;

use cadenzajon\stripecart\Plugin;
use craft\web\Controller;
use yii\web\Response;

class CartController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionAdd(): Response
    {
        $this->requirePostRequest();
        $productId = (int)$this->request->getRequiredBodyParam('productId');
        $qty = (int)$this->request->getBodyParam('qty', 1);

        Plugin::getInstance()->cart->add($productId, $qty);

        return $this->respond('Added to cart.');
    }

    public function actionUpdate(): Response
    {
        $this->requirePostRequest();
        $productId = (int)$this->request->getRequiredBodyParam('productId');
        $qty = (int)$this->request->getRequiredBodyParam('qty');

        Plugin::getInstance()->cart->update($productId, $qty);

        return $this->respond('Cart updated.');
    }

    public function actionRemove(): Response
    {
        $this->requirePostRequest();
        $productId = (int)$this->request->getRequiredBodyParam('productId');

        Plugin::getInstance()->cart->remove($productId);

        return $this->respond('Removed from cart.');
    }

    public function actionClear(): Response
    {
        $this->requirePostRequest();
        Plugin::getInstance()->cart->clear();

        return $this->respond('Cart cleared.');
    }

    private function respond(string $message): Response
    {
        if ($this->request->getAcceptsJson()) {
            return $this->asJson([
                'message' => $message,
                'count' => Plugin::getInstance()->cart->getCount(),
            ]);
        }

        $this->setSuccessFlash($message);

        return $this->redirectToPostedUrl();
    }
}
