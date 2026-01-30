<?php

namespace Marti\Frontend;

use Mia\SDK\MiaClient;
use Marti\Frontend\Controllers\Frontend\ProductController;
use Marti\Frontend\Controllers\Frontend\CartController;
use Marti\Frontend\Controllers\Frontend\CheckoutController;
use Marti\Frontend\Controllers\Frontend\AccountController;
use Marti\Frontend\Controllers\Frontend\PageController;

class FrontendRouter
{
    private MiaClient $client;
    private View $view;
    private ?string $cartId = null;

    public function __construct(MiaClient $client, View $view)
    {
        $this->client = $client;
        $this->view = $view;
        
        // Initialize HTML resources
        HtmlResources::getInstance()->addDefaults();
        HtmlResources::getInstance()->setTitle('OxWinches - Premium Winches & Recovery Equipment');
        
        // Log session state
        error_log("FrontendRouter constructor - Session ID: " . session_id());
        error_log("FrontendRouter constructor - Session cart_id: " . ($_SESSION['cart_id'] ?? 'NOT SET'));
        
        // Ensure cart exists and is in session
        $this->cartId = $_SESSION['cart_id'] ?? null;
        if (!$this->cartId) {
            error_log("No cart_id in session, creating new cart");
            $this->createNewCart();
        } else {
            error_log("Using existing cart_id from session: {$this->cartId}");
        }
    }

    public function handleRequest(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // Handle API requests
        if (str_starts_with($path, '/api/')) {
            $this->handleApiRequest($path, $method);
            return;
        }

        // Handle health check
        if ($path === '/health') {
            $this->handleHealthCheck();
            return;
        }
        
        // Handle SEO-friendly product URLs: /product/{UUID}/{slug}
        if (preg_match('/^\/product\/([a-f0-9\-]{36})\/([^\/]+)$/', $path, $matches)) {
            $_GET['id'] = $matches[1]; // Set the product ID for the controller
            $controller = new ProductController($this->client, $this->view);
            $controller->show();
            return;
        }

        // Route to appropriate controller
        $this->route($path, $method);
    }

    private function route(string $path, string $method): void
    {
        // Handle legacy product URLs and redirect to SEO-friendly format
        if ($path === '/product' && !empty($_GET['id'])) {
            $this->redirectToSeoUrl($_GET['id']);
            return;
        }
        
        // Product routes
        if ($path === '/products') {
            $controller = new ProductController($this->client, $this->view);
            $controller->index();
            return;
        }

        if ($path === '/product') {
            $controller = new ProductController($this->client, $this->view);
            $controller->show();
            return;
        }

        // Cart routes
        if ($path === '/cart') {
            $controller = new CartController($this->client, $this->view);
            $controller->show();
            return;
        }

        // Checkout routes
        if ($path === '/checkout') {
            $controller = new CheckoutController($this->client, $this->view);
            $controller->showCheckout();
            return;
        }

        if ($path === '/checkout/complete') {
            $controller = new CheckoutController($this->client, $this->view);
            $controller->showCheckoutComplete();
            return;
        }

        // Account routes
        if ($path === '/login') {
            $controller = new AccountController($this->client, $this->view);
            if ($method === 'POST') {
                $controller->handleLogin();
            } else {
                $controller->showLogin();
            }
            return;
        }

        if ($path === '/register') {
            $controller = new AccountController($this->client, $this->view);
            if ($method === 'POST') {
                $controller->handleRegister();
            } else {
                $controller->showRegister();
            }
            return;
        }

        if ($path === '/logout') {
            $controller = new AccountController($this->client, $this->view);
            $controller->handleLogout();
            return;
        }

        if ($path === '/account') {
            $controller = new AccountController($this->client, $this->view);
            $controller->showAccount();
            return;
        }

        if ($path === '/orders') {
            $controller = new AccountController($this->client, $this->view);
            // Check if it's order details
            if (!empty($_GET['id'])) {
                $controller->showOrderDetails();
            } else {
                $controller->showOrders();
            }
            return;
        }

        // Static page routes
        if ($path === '/') {
            $controller = new PageController($this->client, $this->view);
            $controller->home();
            return;
        }

        if ($path === '/about') {
            $controller = new PageController($this->client, $this->view);
            $controller->about();
            return;
        }

        if ($path === '/contact') {
            $controller = new PageController($this->client, $this->view);
            $controller->contact();
            return;
        }

        if ($path === '/help') {
            $controller = new PageController($this->client, $this->view);
            $controller->help();
            return;
        }

        if ($path === '/privacy') {
            $controller = new PageController($this->client, $this->view);
            $controller->privacy();
            return;
        }

        if ($path === '/terms') {
            $controller = new PageController($this->client, $this->view);
            $controller->terms();
            return;
        }

        // 404
        $controller = new PageController($this->client, $this->view);
        $controller->show404();
    }

    private function handleApiRequest(string $path, string $method): void
    {
        header('Content-Type: application/json');
        
        try {
            // Cart API
            if ($path === '/api/cart') {
                $controller = new CartController($this->client, $this->view);
                $controller->apiGet();
                return;
            }

            if ($path === '/api/cart/add' && $method === 'POST') {
                $controller = new CartController($this->client, $this->view);
                $controller->apiAdd();
                return;
            }

            if ($path === '/api/cart/update' && $method === 'POST') {
                $controller = new CartController($this->client, $this->view);
                $controller->apiUpdate();
                return;
            }

            if ($path === '/api/cart/remove' && $method === 'POST') {
                $controller = new CartController($this->client, $this->view);
                $controller->apiRemove();
                return;
            }

            if ($path === '/api/cart/save-basket' && $method === 'POST') {
                $controller = new CartController($this->client, $this->view);
                $controller->apiSaveBasket();
                return;
            }

            if ($path === '/api/cart/load-basket' && $method === 'POST') {
                $controller = new CartController($this->client, $this->view);
                $controller->apiLoadBasket();
                return;
            }

            if ($path === '/api/cart/delete-basket' && $method === 'POST') {
                $controller = new CartController($this->client, $this->view);
                $controller->apiDeleteBasket();
                return;
            }

            // Customer API
            if ($path === '/api/customer/update-shipping-address' && $method === 'POST') {
                $controller = new AccountController($this->client, $this->view);
                $controller->apiUpdateShippingAddress();
                return;
            }

            // Categories API
            if ($path === '/api/categories') {
                $controller = new PageController($this->client, $this->view);
                $controller->apiGetCategories();
                return;
            }

            // Not found
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found']);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handleHealthCheck(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'healthy',
            'timestamp' => time()
        ]);
    }

    private function createNewCart(): void
    {
        try {
            error_log("Attempting to create cart for site: " . $this->client->getSiteId());
            error_log("Auth token set: " . (!empty($_SESSION['auth_token']) ? 'YES' : 'NO'));
            error_log("Session ID: " . session_id());
            
            $cart = $this->client->cart->createCart();
            $_SESSION['cart_id'] = $cart['id'];
            $this->cartId = $cart['id'];
            
            error_log("Created new cart with ID: {$this->cartId}");
            error_log("Session cart_id saved: " . $_SESSION['cart_id']);
        } catch (\Exception $e) {
            error_log("Failed to create cart: " . $e->getMessage());
        }
    }
    
    private function redirectToSeoUrl(string $productId): void
    {
        try {
            $product = $this->client->products->getProduct($productId);
            $slug = $this->generateProductSlug($product['title'] ?? 'product');
            $seoUrl = "/product/{$productId}/{$slug}";
            
            header("Location: {$seoUrl}", true, 301); // 301 permanent redirect for SEO
            exit;
        } catch (\Exception $e) {
            // If we can't fetch the product, show 404
            $controller = new PageController($this->client, $this->view);
            $controller->show404();
        }
    }
    
    private function generateProductSlug(string $title): string
    {
        // Convert to lowercase and replace non-alphanumeric characters with hyphens
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Limit length to 50 characters for reasonable URLs
        if (strlen($slug) > 50) {
            $slug = substr($slug, 0, 50);
            $slug = rtrim($slug, '-');
        }
        
        return $slug ?: 'product';
    }
}
