<?php

namespace Marti\Frontend\Controllers\Frontend;

use Mia\SDK\MiaClient;
use Marti\Frontend\View;

abstract class BaseController
{
    protected MiaClient $client;
    protected View $view;
    protected ?string $cartId = null;

    public function __construct(MiaClient $client, View $view)
    {
        $this->client = $client;
        $this->view = $view;
        
        error_log("BaseController __construct - Session ID: " . session_id());
        error_log("BaseController __construct - Session cart_id: " . ($_SESSION['cart_id'] ?? 'NOT SET'));
        
        $this->cartId = $_SESSION['cart_id'] ?? null;
        
        error_log("BaseController __construct - Controller cartId: " . ($this->cartId ?? 'NULL'));
    }

    protected function isLoggedIn(): bool
    {
        return !empty($_SESSION['auth_token']);
    }

    protected function getCustomer(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        try {
            return $this->client->customer->getProfile();
        } catch (\Exception $e) {
            error_log("Failed to get customer: " . $e->getMessage());
            return null;
        }
    }

    protected function getCartItemCount(): int
    {
        if (!$this->cartId) {
            return 0;
        }

        try {
            $cart = $this->client->cart->getCart($this->cartId);
            return array_sum(array_column($cart['items'] ?? [], 'qty'));
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    protected function renderLayout(string $template, array $data = [], string $title = 'OxWinches'): void
    {
        $content = $this->view->render($template, $data);
        
        echo $this->view->renderLayout('layout', $content, [
            'title' => $title,
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }

    protected function showError(string $message, int $code = 500): void
    {
        http_response_code($code);
        $content = $this->view->render('error', ['message' => $message]);
        
        echo $this->view->renderLayout('layout', $content, [
            'title' => 'Error - OxWinches',
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }

    protected function show404(): void
    {
        http_response_code(404);
        $content = $this->view->render('404');
        
        echo $this->view->renderLayout('layout', $content, [
            'title' => '404 Not Found - OxWinches',
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }
}
