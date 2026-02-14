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
        
        $this->cartId = $_SESSION['cart_id'] ?? null;
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
            // First, try to decode JWT to get user info (works for all user types)
            $token = $_SESSION['auth_token'];
            $tokenParts = explode('.', $token);
            
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode($tokenParts[1]), true);
                
                // If user is an admin (site_admin or super_admin), return JWT data
                if (isset($payload['role']) && in_array($payload['role'], ['site_admin', 'super_admin'])) {
                    return [
                        'id' => $payload['sub'] ?? $payload['userId'] ?? null,
                        'email' => $payload['email'] ?? null,
                        'firstName' => $payload['firstName'] ?? $payload['name'] ?? 'Admin',
                        'lastName' => $payload['lastName'] ?? '',
                        'role' => $payload['role']
                    ];
                }
            }
            
            // For regular customers, fetch full profile from API
            $customer = $this->client->customer->getProfile();
            return $customer;
        } catch (\Exception $e) {
            error_log("Failed to get customer: " . $e->getMessage());
            
            // Fallback: try to get basic info from JWT even if API call fails
            try {
                $token = $_SESSION['auth_token'];
                $tokenParts = explode('.', $token);
                
                if (count($tokenParts) === 3) {
                    $payload = json_decode(base64_decode($tokenParts[1]), true);
                    return [
                        'id' => $payload['sub'] ?? $payload['userId'] ?? null,
                        'email' => $payload['email'] ?? null,
                        'firstName' => $payload['firstName'] ?? $payload['name'] ?? 'User',
                        'lastName' => $payload['lastName'] ?? '',
                        'role' => $payload['role'] ?? 'customer'
                    ];
                }
            } catch (\Exception $jwtError) {
                // JWT decode failed
            }
            
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

    protected function getMenuCategories(): array
    {
        try {
            $setting = $this->client->siteSettings->getSetting('menu_categories');
            if (isset($setting['value'])) {
                // Decode if it's a JSON string
                if (is_string($setting['value'])) {
                    $decoded = json_decode($setting['value'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $categories = $decoded;
                    } else {
                        $categories = [];
                    }
                } elseif (is_array($setting['value'])) {
                    $categories = $setting['value'];
                } else {
                    $categories = [];
                }
                
                // Filter out empty category names
                $categories = array_filter($categories, function($cat) {
                    return !empty(trim($cat));
                });
                
                return array_values($categories);
            }
        } catch (\Exception $e) {
            error_log("Failed to load menu_categories setting: " . $e->getMessage());
        }
        
        return [];
    }

    protected function getSupportedCountries(): array
    {
        try {
            // Setting name is "Supported Countries" (with spaces and capitals)
            $setting = $this->client->siteSettings->getSetting('Supported Countries');
            if (isset($setting['value']) && is_array($setting['value'])) {
                return $setting['value'];
            }
        } catch (\Exception $e) {
            // Setting not found, use fallback
        }
        
        // Default fallback countries
        return [
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France'
        ];
    }

    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    protected function renderLayout(string $template, array $data = [], string $title = 'OxWinches'): void
    {
        $content = $this->view->render($template, $data);
        
        $layoutData = [
            'title' => $title,
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn(),
            'menuCategories' => $this->getMenuCategories()
        ];
        
        // Merge any additional data from the template (like jsConfig)
        if (isset($data['jsConfig'])) {
            $layoutData['jsConfig'] = $data['jsConfig'];
        }
        
        echo $this->view->renderLayout('layout', $content, $layoutData);
    }

    protected function showError(string $message, int $code = 500): void
    {
        http_response_code($code);
        $content = $this->view->render('error', ['message' => $message]);
        
        echo $this->view->renderLayout('layout', $content, [
            'title' => 'Error - OxWinches',
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn(),
            'menuCategories' => $this->getMenuCategories()
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
            'isLoggedIn' => $this->isLoggedIn(),
            'menuCategories' => $this->getMenuCategories()
        ]);
    }
}
