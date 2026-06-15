<?php

namespace Marti\Frontend;

use Mia\SDK\MiaClient;
use Marti\Frontend\Controllers\Frontend\ProductController;
use Marti\Frontend\Controllers\Frontend\CartController;
use Marti\Frontend\Controllers\Frontend\CheckoutController;
use Marti\Frontend\Controllers\Frontend\AccountController;
use Marti\Frontend\Controllers\Frontend\PageController;
use Marti\Frontend\Controllers\Frontend\ConfiguratorController;

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
        
        // Set default title from settings (will be overridden by specific pages)
        $siteId = $_ENV['MIA_SITE_ID'] ?? getenv('MIA_SITE_ID');
        if ($siteId) {
            $settingsCache = new SettingsCache($siteId);
            $titleSetting = $settingsCache->get('SEO:home_title');
            if ($titleSetting && isset($titleSetting['value'])) {
                HtmlResources::getInstance()->setTitle($titleSetting['value']);
            } else {
                // Fallback to company name
                $companyName = $settingsCache->get('BRAND:company_name');
                $tagline = $settingsCache->get('BRAND:hero_subtitle');
                if ($companyName && isset($companyName['value'])) {
                    $title = $companyName['value'];
                    if ($tagline && isset($tagline['value'])) {
                        $title .= ' - ' . $tagline['value'];
                    }
                    HtmlResources::getInstance()->setTitle($title);
                }
            }
        }
        
        // Ensure cart exists and is in session
        $this->cartId = $_SESSION['cart_id'] ?? null;
        if (!$this->cartId) {
            $this->createNewCart();
        }
    }

    public function handleRequest(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // Ensure catalogue is synced from S3 (checks once per day)
        if (getenv('CATALOGUE_S3_BUCKET')) {
            $sync = new CatalogueSync(__DIR__ . '/../data');
            $sync->ensureCatalogue();
        }

        // Handle API requests
        if (str_starts_with($path, '/api/')) {
            $this->handleApiRequest($path, $method);
            return;
        }

        // Redirect /images/systems/ to CDN
        if (str_starts_with($path, '/images/systems/')) {
            $filename = substr($path, strlen('/images/systems/'));
            $mapFile = __DIR__ . '/../data/image_url_map.json';
            if (file_exists($mapFile)) {
                $map = json_decode(file_get_contents($mapFile), true);
                if (isset($map[$filename])) {
                    $cdnUrl = is_string($map[$filename]) ? $map[$filename] : ($map[$filename]['cdnUrl'] ?? null);
                    if ($cdnUrl) {
                        $cdnUrl = str_replace('d9yeis51ekfh8.cloudfront.net', 'images.miaai.me', $cdnUrl);
                        header("Location: {$cdnUrl}", true, 301);
                        return;
                    }
                }
            }
            http_response_code(404);
            echo 'Image not found';
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
        // Remove trailing slash for consistent routing (except for root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        
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

        // Category routes
        if ($path === '/categories') {
            $controller = new PageController($this->client, $this->view);
            $controller->categories();
            return;
        }

        // Category route - /category/{slug}
        if (preg_match('#^/category/(.+)$#', $path, $matches)) {
            $categorySlug = $matches[1];
            $controller = new PageController($this->client, $this->view);
            $controller->category($categorySlug);
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

        // Order details route - /orders/{uuid}
        if (preg_match('#^/orders/([a-f0-9\-]{36})$#', $path, $matches)) {
            $_GET['id'] = $matches[1]; // Set the order ID for the controller
            $controller = new AccountController($this->client, $this->view);
            $controller->showOrderDetails();
            return;
        }

        // Configurator routes
        if ($path === '/systems' || str_starts_with($path, '/systems/')) {
            // Redirect old query string URLs to new clean URLs
            if ($path === '/systems' && (!empty($_GET['make']) || !empty($_GET['model']) || !empty($_GET['variant']))) {
                $segments = '/systems';
                if (!empty($_GET['make'])) $segments .= '/' . rawurlencode($_GET['make']);
                if (!empty($_GET['model'])) $segments .= '/' . rawurlencode($_GET['model']);
                if (!empty($_GET['variant'])) $segments .= '/' . rawurlencode($this->slugifyVariant($_GET['variant']));
                header("Location: {$segments}", true, 301);
                return;
            }
            $controller = new ConfiguratorController($this->client, $this->view);
            if (str_starts_with($path, '/systems/system/')) {
                // Legacy URL redirect: /systems/system/SSXFD097/slug -> new format
                $systemPath = substr($path, strlen('/systems/system/'));
                $systemParts = explode('/', $systemPath, 2);
                $systemNumber = urldecode($systemParts[0]);
                // Redirect to new format (let showSystem handle it with just the number for now)
                $_GET['system'] = $systemNumber;
                $controller->showSystem();
            } elseif ($path === '/systems/system' && !empty($_GET['system'])) {
                $_GET['system'] = $_GET['system'];
                $controller->showSystem();
            } else {
                // Parse clean URL segments: /systems/{make}/{model}/{variant-slug}/{system-name}/{system-number}
                $segments = explode('/', trim($path, '/'));
                // segments[0] = 'systems'
                if (isset($segments[1]) && $segments[1] !== '') $_GET['make'] = urldecode($segments[1]);
                if (isset($segments[2]) && $segments[2] !== '') $_GET['model'] = urldecode($segments[2]);
                if (isset($segments[3]) && $segments[3] !== '') $_GET['variantSlug'] = urldecode($segments[3]);
                // If 5 segments: [4] = system-name-slug, [5] = system number
                if (isset($segments[5]) && $segments[5] !== '') {
                    $_GET['system'] = urldecode($segments[5]);
                    $controller->showSystem();
                } else {
                    $controller->index();
                }
            }
            return;
        }

        // Redirect old /configurator URLs to /systems
        if ($path === '/configurator' || str_starts_with($path, '/configurator/')) {
            $newPath = '/systems' . substr($path, strlen('/configurator'));
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            header("Location: {$newPath}" . ($qs ? "?{$qs}" : ''), true, 301);
            return;
        }

        // Search route
        if ($path === '/search') {
            $controller = new \Marti\Frontend\Controllers\Frontend\SearchController($this->client, $this->view);
            $controller->index();
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

            if ($path === '/api/cart/save-guest-address' && $method === 'POST') {
                $controller = new CartController($this->client, $this->view);
                $controller->apiSaveGuestAddress();
                return;
            }

            if ($path === '/api/cart/save-guest-email' && $method === 'POST') {
                $controller = new CartController($this->client, $this->view);
                $controller->apiSaveGuestEmail();
                return;
            }

            // Customer API
            if ($path === '/api/customer/update-shipping-address' && $method === 'POST') {
                $controller = new AccountController($this->client, $this->view);
                $controller->apiUpdateShippingAddress();
                return;
            }
            
            if ($path === '/api/customer/update-profile' && $method === 'POST') {
                $controller = new AccountController($this->client, $this->view);
                $controller->apiUpdateProfile();
                return;
            }

            // Categories API
            if ($path === '/api/categories') {
                $controller = new PageController($this->client, $this->view);
                $controller->apiGetCategories();
                return;
            }

            // Configurator API
            if ($path === '/api/configurator/options') {
                $controller = new ConfiguratorController($this->client, $this->view);
                $controller->apiGetOptions();
                return;
            }

            // Product API
            if (preg_match('#^/api/products/([a-f0-9-]+)$#', $path, $matches)) {
                $controller = new \Marti\Frontend\Controllers\Frontend\ProductController($this->client, $this->view);
                $controller->apiGetProduct($matches[1]);
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
            $cart = $this->client->cart->createCart();
            $_SESSION['cart_id'] = $cart['id'];
            $this->cartId = $cart['id'];
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

    private function slugifyVariant(string $variant): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $variant));
        return trim($slug, '-');
    }
}
