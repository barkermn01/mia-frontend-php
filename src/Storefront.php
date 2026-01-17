<?php

namespace Marti\Frontend;

use Mia\SDK\MiaClient;
use Mia\SDK\Exceptions\MiaException;
use Mia\SDK\Exceptions\AuthenticationException;
use Mia\SDK\Exceptions\ValidationException;
use Mia\SDK\Exceptions\NotFoundException;

class Storefront
{
    private $client;
    private $cache;
    private $view;
    private $cartId;
    private $config;

    public function __construct()
    {
        $this->loadConfig();
        $this->initializeClient();  // Initialize client first
        $this->initializeCache();
        $this->initializeSession(); // Then session (which may need client for cart creation)
        $this->initializeView();
    }

    private function loadConfig(): void
    {
        // Load .env file if it exists
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue; // Skip comments and invalid lines
                }
                
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                // Set environment variable if not already set
                if (!isset($_ENV[$key]) && getenv($key) === false) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }

        $this->config = [
            'api_url' => $_ENV['MIA_API_URL'] ?? getenv('MIA_API_URL') ?: 'https://api.miaai.me',
            'site_id' => $_ENV['MIA_SITE_ID'] ?? getenv('MIA_SITE_ID') ?: null,
            'verify_ssl' => filter_var($_ENV['MIA_VERIFY_SSL'] ?? getenv('MIA_VERIFY_SSL') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'debug' => filter_var($_ENV['MIA_DEBUG'] ?? getenv('MIA_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        ];

        if (!$this->config['site_id']) {
            // Debug information
            error_log("MIA_SITE_ID not found. Available env vars: " . print_r($_ENV, true));
            error_log("getenv MIA_SITE_ID: " . var_export(getenv('MIA_SITE_ID'), true));
            throw new \Exception('MIA_SITE_ID environment variable is required. Please set it in your .env file. Current value: ' . var_export($this->config['site_id'], true));
        }
    }

    private function initializeClient(): void
    {
        $this->client = new MiaClient([
            'apiUrl' => $this->config['api_url'],
            'siteId' => $this->config['site_id'],
            'verify_ssl' => $this->config['verify_ssl'],
            'debug' => false // Always disable debug to avoid cURL warnings
        ]);

        // Set auth token if customer is logged in (session may not be started yet)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        error_log("initializeClient - Session auth_token: " . ($_SESSION['auth_token'] ?? 'NOT SET'));
        if (isset($_SESSION['auth_token'])) {
            $this->client->setAuthToken($_SESSION['auth_token']);
            error_log("initializeClient - Auth token set on client: " . substr($_SESSION['auth_token'], 0, 20) . '...');
        } else {
            error_log("initializeClient - No auth token in session");
        }
    }

    private function initializeCache(): void
    {
        // No external cache dependency - use simple in-memory cache or no cache
        $this->cache = null;
    }

    private function initializeView(): void
    {
        $this->view = new View(__DIR__ . '/templates');
        
        // Initialize HTML resources with defaults
        HtmlResources::getInstance()->addDefaults();
    }

    private function initializeSession(): void
    {
        // Session should already be started by initializeClient()
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        error_log("Session cart_id: " . ($_SESSION['cart_id'] ?? 'NOT SET'));

        // Initialize or retrieve cart
        if (!isset($_SESSION['cart_id'])) {
            error_log("No cart_id in session, creating new cart");
            $this->createNewCart();
        } else {
            $this->cartId = $_SESSION['cart_id'];
            error_log("Using existing cart_id from session: " . $this->cartId);
            
            // If cart_id looks like a mock/temp cart, clear it and create a new one
            if (strpos($this->cartId, 'mock-cart-') === 0 || strpos($this->cartId, 'temp-cart-') === 0) {
                error_log("Found invalid mock/temp cart_id, clearing and creating new cart");
                unset($_SESSION['cart_id']);
                $this->createNewCart();
            }
        }
    }

    private function createNewCart(): void
    {
        try {
            error_log("Attempting to create cart with API URL: " . $this->config['api_url']);
            error_log("Site ID: " . $this->config['site_id']);
            error_log("Auth token set: " . ($this->client->getAuthToken() ? 'YES' : 'NO'));
            
            $cart = $this->client->cart->createCart();
            $this->cartId = $cart['id'];
            $_SESSION['cart_id'] = $this->cartId;
            error_log("Created new cart with ID: " . $this->cartId);
        } catch (\Exception $e) {
            error_log("Failed to create cart: " . $e->getMessage());
            error_log("Exception type: " . get_class($e));
            throw new \Exception("Cannot create cart: " . $e->getMessage());
        }
    }

    /**
     * Generate a URL-safe slug from product title
     */
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

    /**
     * Generate SEO-friendly product URL
     */
    public function generateProductUrl(array $product): string
    {
        $id = $product['id'] ?? $product['uuid'] ?? '';
        $title = $product['title'] ?? 'Product';
        $slug = $this->generateProductSlug($title);
        
        return "/product/{$id}/{$slug}";
    }

    /**
     * Redirect legacy product URLs to SEO-friendly format
     */
    private function redirectToSeoUrl(string $productId): void
    {
        try {
            $product = $this->client->products->getProduct($productId);
            $seoUrl = $this->generateProductUrl($product);
            
            header("Location: {$seoUrl}", true, 301); // 301 permanent redirect for SEO
            exit;
        } catch (\Exception $e) {
            // If we can't fetch the product, fall back to showing 404
            $this->show404();
        }
    }

    public function handleRequest(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // Handle API routes for AJAX
        if (strpos($path, '/api/') === 0) {
            $this->handleApiRequest($path, $method);
            return;
        }

        // Handle SEO-friendly product URLs: /product/{UUID}/{slug}
        if (preg_match('/^\/product\/([a-f0-9\-]{36})\/([^\/]+)$/', $path, $matches)) {
            $_GET['id'] = $matches[1]; // Set the product ID for the showProduct method
            $this->showProduct();
            return;
        }

        // Handle legacy product URLs and redirect to SEO-friendly format
        if ($path === '/product' && !empty($_GET['id'])) {
            $this->redirectToSeoUrl($_GET['id']);
            return;
        }

        // Route the request
        switch ($path) {
            case '/health':
                $this->handleHealthCheck();
                break;
            case '/':
                $this->showHomePage();
                break;
            case '/products':
                $this->showProducts();
                break;
            case '/product':
                $this->showProduct();
                break;
            case '/cart':
                $this->showCart();
                break;
            case '/checkout':
                $this->showCheckout();
                break;
            case '/login':
                if ($method === 'POST') {
                    $this->handleLogin();
                } else {
                    $this->showLogin();
                }
                break;
            case '/register':
                if ($method === 'POST') {
                    $this->handleRegister();
                } else {
                    $this->showRegister();
                }
                break;
            case '/logout':
                $this->handleLogout();
                break;
            case '/account':
                $this->showAccount();
                break;
            case '/orders':
                $this->showOrders();
                break;
            default:
                $this->show404();
        }
    }

    private function handleApiRequest(string $path, string $method): void
    {
        header('Content-Type: application/json');
        
        try {
            switch ($path) {
                case '/api/cart':
                    $this->apiGetCart();
                    break;
                case '/api/cart/add':
                    if ($method === 'POST') {
                        $this->apiAddToCart();
                    }
                    break;
                case '/api/cart/update':
                    if ($method === 'POST') {
                        $this->apiUpdateCart();
                    }
                    break;
                case '/api/cart/remove':
                    if ($method === 'POST') {
                        $this->apiRemoveFromCart();
                    }
                    break;
                case '/api/categories':
                    $this->apiGetCategories();
                    break;
                case '/api/customer/update-shipping-address':
                    if ($method === 'POST') {
                        $this->apiUpdateShippingAddress();
                    }
                    break;
                case '/api/cart/save-basket':
                    if ($method === 'POST') {
                        $this->apiSaveBasket();
                    }
                    break;
                case '/api/cart/load-basket':
                    if ($method === 'POST') {
                        $this->apiLoadBasket();
                    }
                    break;
                case '/api/cart/delete-basket':
                    if ($method === 'POST') {
                        $this->apiDeleteBasket();
                    }
                    break;
                default:
                    http_response_code(404);
                    echo json_encode(['error' => 'API endpoint not found']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function apiGetCart(): void
    {
        try {
            $cart = $this->client->cart->getCart($this->cartId);
            
            // Enrich cart items with product data (title, image)
            if (!empty($cart['items'])) {
                foreach ($cart['items'] as &$item) {
                    try {
                        // Get product details using the productId from cart item
                        $product = $this->client->products->getProduct($item['productId']);
                        $item['productTitle'] = $product['title'] ?? $item['sku'];
                        $item['title'] = $product['title'] ?? $item['sku'];
                        // Get first image from images array
                        $item['image'] = !empty($product['images']) ? $product['images'][0] : null;
                        
                        // Find the specific variant by SKU to get the display name
                        // Only show variant if product has multiple variants
                        $item['variantName'] = null;
                        if (!empty($product['variants']) && count($product['variants']) > 1) {
                            foreach ($product['variants'] as $variant) {
                                if ($variant['sku'] === $item['sku']) {
                                    $item['variantName'] = $variant['presentableName'] ?? null;
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // If we can't fetch product details, just use SKU
                        error_log("Failed to enrich cart item {$item['sku']}: " . $e->getMessage());
                        $item['title'] = $item['sku'];
                        $item['productTitle'] = $item['sku'];
                    }
                }
                unset($item); // Break reference
            }
            
            error_log("Cart data: " . json_encode($cart));
            $html = $this->view->render('cart-sidebar', ['cart' => $cart]);
            
            echo json_encode([
                'success' => true,
                'html' => $html,
                'cartCount' => $this->getCartItemCount()
            ]);
        } catch (MiaException $e) {
            error_log("Cart API error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function apiAddToCart(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $sku = $input['sku'] ?? '';
        $qty = (int)($input['qty'] ?? 1);
        
        error_log("Add to cart request - SKU: $sku, Qty: $qty, Cart ID: " . $this->cartId);
        
        if (!$sku) {
            echo json_encode(['success' => false, 'message' => 'SKU is required']);
            return;
        }

        try {
            $result = $this->client->cart->addToCart($this->cartId, $sku, $qty);
            error_log("Add to cart success: " . json_encode($result));
            echo json_encode([
                'success' => true,
                'cartCount' => $this->getCartItemCount()
            ]);
        } catch (MiaException $e) {
            error_log("Add to cart error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function apiUpdateCart(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $itemId = $input['itemId'] ?? '';
        $qty = (int)($input['qty'] ?? 0);
        
        if (!$itemId) {
            echo json_encode(['success' => false, 'message' => 'Item ID is required']);
            return;
        }

        try {
            if ($qty > 0) {
                $this->client->cart->updateCartItem($this->cartId, $itemId, $qty);
            } else {
                $this->client->cart->removeCartItem($this->cartId, $itemId);
            }
            
            echo json_encode([
                'success' => true,
                'cartCount' => $this->getCartItemCount()
            ]);
        } catch (MiaException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function apiRemoveFromCart(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $itemId = $input['itemId'] ?? '';
        
        if (!$itemId) {
            echo json_encode(['success' => false, 'message' => 'Item ID is required']);
            return;
        }

        try {
            $this->client->cart->removeCartItem($this->cartId, $itemId);
            echo json_encode([
                'success' => true,
                'cartCount' => $this->getCartItemCount()
            ]);
        } catch (MiaException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function apiGetCategories(): void
    {
        try {
            $categories = $this->client->products->getCategories(['status' => 'active']);
            echo json_encode([
                'success' => true,
                'categories' => $categories['categories'] ?? [],
                'total' => $categories['total'] ?? 0
            ]);
        } catch (MiaException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function apiUpdateShippingAddress(): void
    {
        try {
            error_log("=== UPDATE SHIPPING ADDRESS START ===");
            
            if (!$this->isLoggedIn()) {
                error_log("Not logged in");
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit;
            }
            
            error_log("User is logged in");
            
            $input = json_decode(file_get_contents('php://input'), true);
            error_log("Input received: " . json_encode($input));
            
            $shippingAddress = $input['shippingAddress'] ?? null;
            
            if (!$shippingAddress) {
                error_log("No shipping address in input");
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Shipping address is required']);
                exit;
            }
            
            // Ensure auth token is set on client
            if (isset($_SESSION['auth_token'])) {
                $this->client->setAuthToken($_SESSION['auth_token']);
                error_log("Auth token set: " . substr($_SESSION['auth_token'], 0, 20) . '...');
            } else {
                error_log("No auth token in session!");
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Authentication token not found']);
                exit;
            }
            
            error_log("Calling updateProfile with address: " . json_encode($shippingAddress));
            
            $result = $this->client->customer->updateProfile([
                'shippingAddress' => $shippingAddress
            ]);
            
            error_log("Update profile result: " . json_encode($result));
            
            // Update session customer data
            if (!isset($_SESSION['customer'])) {
                $_SESSION['customer'] = [];
            }
            $_SESSION['customer']['shippingAddress'] = $shippingAddress;
            
            error_log("Session updated, sending success response");
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Shipping address updated']);
            exit;
            
        } catch (MiaException $e) {
            error_log("MiaException: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        } catch (\Exception $e) {
            error_log("General Exception: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
            exit;
        }
    }

    private function apiSaveBasket(): void
    {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $basketName = $input['name'] ?? '';
        
        if (!$basketName) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Basket name is required']);
            exit;
        }
        
        try {
            // Ensure auth token is set
            if (isset($_SESSION['auth_token'])) {
                $this->client->setAuthToken($_SESSION['auth_token']);
            }
            
            error_log("Saving basket with cart ID: " . $this->cartId . ", name: " . $basketName);
            
            // Verify cart exists first
            $cart = $this->client->cart->getCart($this->cartId);
            error_log("Cart verified, has " . count($cart['items'] ?? []) . " items");
            
            $result = $this->client->cart->saveBasket([
                'cartId' => $this->cartId,
                'name' => $basketName,
                'displayName' => $basketName
            ]);
            
            error_log("Basket saved successfully: " . json_encode($result));
            
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Basket saved successfully']);
            exit;
        } catch (MiaException $e) {
            error_log("Failed to save basket: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    private function apiLoadBasket(): void
    {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $basketName = $input['basketName'] ?? '';
        
        if (!$basketName) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Basket name is required']);
            exit;
        }
        
        try {
            // Ensure auth token is set
            if (isset($_SESSION['auth_token'])) {
                $this->client->setAuthToken($_SESSION['auth_token']);
            }
            
            $result = $this->client->cart->loadSavedBasket($basketName, [
                'cartId' => $this->cartId
            ]);
            
            http_response_code(200);
            echo json_encode([
                'success' => true, 
                'message' => 'Basket loaded successfully',
                'cartCount' => $this->getCartItemCount()
            ]);
            exit;
        } catch (MiaException $e) {
            error_log("Failed to load basket: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    private function apiDeleteBasket(): void
    {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $basketName = $input['basketName'] ?? '';
        
        if (!$basketName) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Basket name is required']);
            exit;
        }
        
        try {
            // Ensure auth token is set
            if (isset($_SESSION['auth_token'])) {
                $this->client->setAuthToken($_SESSION['auth_token']);
            }
            
            $result = $this->client->cart->deleteSavedBasket($basketName);
            
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Basket deleted successfully']);
            exit;
        } catch (MiaException $e) {
            error_log("Failed to delete basket: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    private function showHomePage(): void
    {
        // Set content type to HTML
        header('Content-Type: text/html; charset=utf-8');
        
        // Set page-specific resources
        $htmlResources = HtmlResources::getInstance();
        $htmlResources->setTitle('Welcome to OxWinches');
        $htmlResources->setDescription('Premium winches and marine equipment powered by Mia AI Store');
        $htmlResources->setKeywords('winches, marine equipment, boat winches, anchor winches, oxwinches');
        
        // Get featured products (products with "Featured" tag)
        $products = $this->getProducts(['tag' => 'Featured', 'limit' => 8]);
        
        $content = $this->view->render('home', [
            'products' => $products
        ]);
        
        echo $this->view->renderLayout('layout', $content, [
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }

    private function showProducts(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $selectedFilters = $_GET['filters'] ?? [];
        
        error_log("URL Parameters - page: $page, search: '$search', category: '$category', filters: " . json_encode($selectedFilters));
        
        // Convert single category to filters array for backward compatibility
        if ($category && !$selectedFilters) {
            $selectedFilters = [$category];
        }
        
        error_log("Selected Filters after processing: " . json_encode($selectedFilters));
        
        $filters = [
            'page' => $page,
            'limit' => 12
        ];
        
        if ($search) {
            $filters['search'] = $search;
        }
        
        // Handle multiple filters with AND logic (comma-separated)
        if ($selectedFilters) {
            if (is_array($selectedFilters)) {
                // Multiple filters - combine them with commas for AND logic
                $filters['category'] = implode(',', $selectedFilters);
            } else {
                // Single filter
                $filters['category'] = $selectedFilters;
            }
        }

        error_log("API Request Filters: " . json_encode($filters));

        $products = $this->getProducts($filters);
        
        // Get categories for sidebar - pass current filters to get updated counts
        $categoryFilters = [];
        if ($selectedFilters) {
            if (is_array($selectedFilters)) {
                $categoryFilters['category'] = implode(',', $selectedFilters);
            } else {
                $categoryFilters['category'] = $selectedFilters;
            }
        }
        if ($search) {
            $categoryFilters['search'] = $search;
        }
        
        $categories = $this->getCategories($categoryFilters);
        
        // Set page-specific resources
        $title = 'Products';
        if ($search) $title .= ' - Search: ' . htmlspecialchars($search);
        if ($selectedFilters) {
            $filterNames = array_map(function($filter) {
                // Show just the value part for filter categories
                if (strpos($filter, ':') !== false) {
                    return explode(':', $filter, 2)[1];
                }
                return $filter;
            }, is_array($selectedFilters) ? $selectedFilters : [$selectedFilters]);
            $title .= ' - Filters: ' . htmlspecialchars(implode(', ', $filterNames));
        }
        $title .= ' - OxWinches';
        
        $htmlResources = HtmlResources::getInstance();
        $htmlResources->setTitle($title);
        $htmlResources->setDescription('Browse our collection of products. Find exactly what you\'re looking for.');
        $htmlResources->setKeywords('products, shop, browse, ' . ($selectedFilters ? htmlspecialchars(implode(', ', is_array($selectedFilters) ? $selectedFilters : [$selectedFilters])) : 'all categories'));
        
        $content = $this->view->render('products', [
            'products' => $products,
            'categories' => $categories,
            'search' => $search,
            'category' => $category, // Keep for backward compatibility
            'selectedFilters' => is_array($selectedFilters) ? $selectedFilters : ($selectedFilters ? [$selectedFilters] : []),
            'page' => $page
        ]);
        
        echo $this->view->renderLayout('layout', $content, [
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }

    private function showProduct(): void
    {
        $id = $_GET['id'] ?? '';
        if (!$id) {
            $this->show404();
            return;
        }

        try {
            error_log("Attempting to fetch product with ID: " . $id);
            $product = $this->client->products->getProduct($id);
            error_log("Product fetched successfully: " . json_encode($product));
            
            $variants = $this->client->products->getProductVariants($id);
            error_log("Variants fetched successfully: " . json_encode($variants));
            
            // Add product-specific resources
            $htmlResources = HtmlResources::getInstance();
            $htmlResources->setTitle(htmlspecialchars($product['title']) . ' - OxWinches');
            $htmlResources->setDescription(strip_tags($product['description'] ?? ''));
            $htmlResources->addJsBody('/js/product.js'); // Product JS needs to be after DOM and config
            
            $content = $this->view->render('product', [
                'product' => $product,
                'variants' => $variants['items'] ?? []
            ]);
            
            echo $this->view->renderLayout('layout', $content, [
                'cartCount' => $this->getCartItemCount(),
                'customer' => $this->getCustomer(),
                'isLoggedIn' => $this->isLoggedIn()
            ]);
        } catch (NotFoundException $e) {
            error_log("Product not found: " . $e->getMessage());
            $this->show404();
        } catch (MiaException $e) {
            error_log("MiaException occurred: " . $e->getMessage());
            error_log("Exception details: " . json_encode([
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            $this->showError("Failed to load product: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("General exception occurred: " . $e->getMessage());
            error_log("Exception details: " . json_encode([
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            $this->showError("Failed to load product: " . $e->getMessage());
        }
    }

    private function showCart(): void
    {
        try {
            // Get fresh customer data from API if logged in
            $customer = null;
            if ($this->isLoggedIn()) {
                try {
                    $customer = $this->client->customer->getProfile();
                    // Update session with fresh data
                    $_SESSION['customer'] = $customer;
                } catch (\Exception $e) {
                    error_log("Failed to fetch customer profile: " . $e->getMessage());
                    // Fall back to session data
                    $customer = $this->getCustomer();
                }
            }
            
            $cart = $this->client->cart->getCart($this->cartId);
            
            // Enrich cart items with product data (title, image)
            if (!empty($cart['items'])) {
                foreach ($cart['items'] as &$item) {
                    try {
                        // Get product details using the productId from cart item
                        $product = $this->client->products->getProduct($item['productId']);
                        $item['productTitle'] = $product['title'] ?? $item['sku'];
                        $item['title'] = $product['title'] ?? $item['sku'];
                        // Get first image from images array
                        $item['image'] = !empty($product['images']) ? $product['images'][0] : null;
                        
                        // Find the specific variant by SKU to get the display name
                        // Only show variant if product has multiple variants
                        $item['variantName'] = null;
                        if (!empty($product['variants']) && count($product['variants']) > 1) {
                            foreach ($product['variants'] as $variant) {
                                if ($variant['sku'] === $item['sku']) {
                                    $item['variantName'] = $variant['presentableName'] ?? null;
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // If we can't fetch product details, just use SKU
                        error_log("Failed to enrich cart item {$item['sku']}: " . $e->getMessage());
                        $item['title'] = $item['sku'];
                        $item['productTitle'] = $item['sku'];
                    }
                }
                unset($item); // Break reference
            }
            
            // Get shipping options based on customer's address or default to GB
            $shippingOptions = null;
            $selectedCountry = $customer['shippingAddress']['country'] ?? 'GB';
            
            try {
                $shippingOptions = $this->client->shipping->getCartShippingOptions($this->cartId, $selectedCountry);
                error_log("Shipping options fetched for country {$selectedCountry}: " . json_encode($shippingOptions));
            } catch (\Exception $e) {
                error_log("Failed to get shipping options for country {$selectedCountry}: " . $e->getMessage());
            }
            
            // Set page-specific resources
            $htmlResources = HtmlResources::getInstance();
            $htmlResources->setTitle('Shopping Cart - OxWinches');
            $htmlResources->setDescription('Review your shopping cart and proceed to checkout.');
            $htmlResources->setKeywords('cart, shopping cart, checkout, review order');
            
            $content = $this->view->render('cart', [
                'cart' => $cart,
                'shippingOptions' => $shippingOptions,
                'selectedCountry' => $selectedCountry,
                'isLoggedIn' => $this->isLoggedIn(),
                'customer' => $customer
            ]);
            
            echo $this->view->renderLayout('layout', $content, [
                'cartCount' => $this->getCartItemCount(),
                'customer' => $customer,
                'isLoggedIn' => $this->isLoggedIn()
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load cart: " . $e->getMessage());
        }
    }

    private function showCheckout(): void
    {
        try {
            $cart = $this->client->cart->getCart($this->cartId);
            $config = $this->client->checkout->getCheckoutConfig();
            
            $content = $this->view->render('checkout', [
                'cart' => $cart,
                'config' => $config
            ]);
            
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Checkout - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => $this->getCustomer(),
                'isLoggedIn' => $this->isLoggedIn()
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load checkout: " . $e->getMessage());
        }
    }

    private function showLogin(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/account');
            return;
        }
        
        $content = $this->view->render('login');
        
        echo $this->view->renderLayout('layout', $content, [
            'title' => 'Login - OxWinches',
            'cartCount' => $this->getCartItemCount(),
            'customer' => null,
            'isLoggedIn' => false
        ]);
    }

    private function handleLogin(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        error_log("Login attempt - Email: " . $email);
        
        if (!$email || !$password) {
            error_log("Login failed - Missing email or password");
            $content = $this->view->render('login', ['error' => 'Email and password are required']);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Login - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
            return;
        }

        try {
            error_log("Attempting login with API URL: " . $this->config['api_url']);
            error_log("Site ID: " . $this->config['site_id']);
            error_log("Login request data: " . json_encode([
                'email' => $email,
                'password' => '***hidden***'
            ]));
            
            $result = $this->client->login([
                'email' => $email,
                'password' => $password,
                'siteId' => $this->config['site_id']
            ]);
            
            error_log("Login successful: " . json_encode($result));
            
            $_SESSION['auth_token'] = $result['token'];
            $_SESSION['customer'] = $result['user'];
            $this->client->setAuthToken($result['token']);
            
            error_log("Session data set - Token: " . substr($result['token'], 0, 20) . "...");
            error_log("Session data set - Customer: " . json_encode($result['user']));
            error_log("Client auth token set: " . ($this->client->getAuthToken() ? 'YES' : 'NO'));
            error_log("Session auth_token: " . ($_SESSION['auth_token'] ?? 'NOT SET'));
            
            error_log("Session data set, redirecting to /account");
            $this->redirect('/account');
        } catch (AuthenticationException $e) {
            error_log("Authentication failed: " . $e->getMessage());
            error_log("AuthenticationException details: " . json_encode([
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]));
            $content = $this->view->render('login', ['error' => 'Invalid email or password']);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Login - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
        } catch (MiaException $e) {
            error_log("Login MiaException: " . $e->getMessage());
            error_log("Exception details: " . json_encode([
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            $content = $this->view->render('login', ['error' => 'Login failed: ' . $e->getMessage()]);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Login - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
        } catch (\Exception $e) {
            error_log("Login general exception: " . $e->getMessage());
            error_log("Exception details: " . json_encode([
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            $content = $this->view->render('login', ['error' => 'Login failed: ' . $e->getMessage()]);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Login - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
        }
    }

    private function showRegister(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/account');
            return;
        }
        
        $content = $this->view->render('register');
        
        echo $this->view->renderLayout('layout', $content, [
            'title' => 'Register - OxWinches',
            'cartCount' => $this->getCartItemCount(),
            'customer' => null,
            'isLoggedIn' => false
        ]);
    }

    private function handleRegister(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        
        if (!$email || !$password || !$firstName || !$lastName) {
            $content = $this->view->render('register', ['error' => 'All fields are required']);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Register - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
            return;
        }

        try {
            $result = $this->client->customer->signup([
                'email' => $email,
                'password' => $password,
                'firstName' => $firstName,
                'lastName' => $lastName
            ]);
            
            $content = $this->view->render('register', ['success' => 'Registration successful! Please check your email to verify your account.']);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Register - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
        } catch (ValidationException $e) {
            $content = $this->view->render('register', ['error' => 'Invalid data: ' . $e->getMessage()]);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Register - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
        } catch (MiaException $e) {
            $content = $this->view->render('register', ['error' => 'Registration failed: ' . $e->getMessage()]);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Register - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
        }
    }

    private function handleLogout(): void
    {
        unset($_SESSION['auth_token']);
        unset($_SESSION['customer']);
        // Don't call client logout due to SDK bug with setAuthToken(null)
        // Just clear the session data which is sufficient for logout
        $this->redirect('/');
    }

    private function showAccount(): void
    {
        error_log("showAccount called");
        error_log("isLoggedIn: " . ($this->isLoggedIn() ? 'YES' : 'NO'));
        error_log("Session auth_token: " . ($_SESSION['auth_token'] ?? 'NOT SET'));
        error_log("Client auth token: " . ($this->client->getAuthToken() ? substr($this->client->getAuthToken(), 0, 20) . '...' : 'NOT SET'));
        
        if (!$this->isLoggedIn()) {
            error_log("Not logged in, redirecting to login");
            $this->redirect('/login');
            return;
        }

        try {
            $customer = $this->getCustomer();
            error_log("Customer data: " . json_encode($customer));
            
            // Check if this is a super_admin or other admin account
            if (isset($customer['role']) && in_array($customer['role'], ['super_admin', 'site_admin'])) {
                error_log("Admin account detected, showing admin account page");
                
                // For admin accounts, we'll show basic account info and admin functions
                $profile = [
                    'id' => $customer['id'],
                    'email' => $customer['email'],
                    'role' => $customer['role'],
                    'status' => $customer['status'] ?? 'active'
                ];
                
                $savedBaskets = []; // Admin accounts don't have saved baskets
                
            } else {
                error_log("Customer account detected, attempting to get profile");
                // For regular customers, get the full profile
                $profile = $this->client->customer->getProfile();
                
                try {
                    $savedBasketsResponse = $this->client->cart->getSavedBaskets();
                    error_log("Saved baskets response: " . json_encode($savedBasketsResponse));
                    $savedBaskets = $savedBasketsResponse['items'] ?? [];
                    error_log("Saved baskets count: " . count($savedBaskets));
                } catch (\Exception $e) {
                    error_log("Failed to fetch saved baskets: " . $e->getMessage());
                    $savedBaskets = [];
                }
            }
            
            error_log("Profile data: " . json_encode($profile));
            
            $content = $this->view->render('account', [
                'profile' => $profile,
                'savedBaskets' => $savedBaskets,
                'isAdmin' => isset($customer['role']) && in_array($customer['role'], ['super_admin', 'site_admin'])
            ]);
            
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'My Account - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => $this->getCustomer(),
                'isLoggedIn' => $this->isLoggedIn()
            ]);
        } catch (MiaException $e) {
            error_log("Account MiaException: " . $e->getMessage());
            error_log("Exception details: " . json_encode([
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
            $this->showError("Failed to load account: " . $e->getMessage());
        }
    }

    private function showOrders(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
            return;
        }

        try {
            $customer = $this->getCustomer();
            
            // Check if this is an admin account
            if (isset($customer['role']) && in_array($customer['role'], ['super_admin', 'site_admin'])) {
                // Admin accounts don't have personal orders, show empty or redirect to admin panel
                $orders = [];
                error_log("Admin account - showing empty orders");
            } else {
                // Regular customers can view their orders
                $ordersResponse = $this->client->orders->getOrders();
                $orders = $ordersResponse['items'] ?? [];
            }
            
            $content = $this->view->render('orders', [
                'orders' => $orders,
                'isAdmin' => isset($customer['role']) && in_array($customer['role'], ['super_admin', 'site_admin'])
            ]);
            
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Order History - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => $this->getCustomer(),
                'isLoggedIn' => $this->isLoggedIn()
            ]);
        } catch (MiaException $e) {
            error_log("Orders MiaException: " . $e->getMessage());
            $this->showError("Failed to load orders: " . $e->getMessage());
        }
    }

    private function getProducts(array $filters = []): array
    {
        try {
            error_log("Calling API with filters: " . json_encode($filters));
            $products = $this->client->products->getProducts($filters);
            error_log("API Response - Total products: " . ($products['total'] ?? 0));
            return $products;
        } catch (MiaException $e) {
            error_log("Failed to fetch products: " . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }

    private function getCategories(array $filters = []): array
    {
        try {
            $categories = $this->client->products->getCategories(array_merge(['status' => 'active'], $filters));
            $allCategories = $categories['categories'] ?? [];
            
            $primaryCategories = [];
            $filterGroups = [];
            
            foreach ($allCategories as $category) {
                if (strpos($category['name'], ':') === false) {
                    // Primary category (no colon)
                    $primaryCategories[] = $category;
                } else {
                    // Filter category (has colon) - group by key
                    $parts = explode(':', $category['name'], 2);
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    
                    if (!isset($filterGroups[$key])) {
                        $filterGroups[$key] = [];
                    }
                    
                    $filterGroups[$key][] = [
                        'name' => $value,
                        'fullName' => $category['name'],
                        'count' => $category['count']
                    ];
                }
            }
            
            return [
                'primary' => $primaryCategories,
                'filters' => $filterGroups
            ];
        } catch (MiaException $e) {
            error_log("Failed to fetch categories: " . $e->getMessage());
            return ['primary' => [], 'filters' => []];
        }
    }

    private function isLoggedIn(): bool
    {
        return isset($_SESSION['auth_token']) && isset($_SESSION['customer']);
    }

    private function getCustomer(): ?array
    {
        return $_SESSION['customer'] ?? null;
    }

    private function getCartItemCount(): int
    {
        try {
            $cart = $this->client->cart->getCart($this->cartId);
            return array_sum(array_column($cart['items'] ?? [], 'qty'));
        } catch (MiaException $e) {
            return 0;
        }
    }

    private function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    private function show404(): void
    {
        http_response_code(404);
        $content = $this->view->render('404');
        echo $this->view->renderLayout('layout', $content, [
            'title' => 'Page Not Found - OxWinches',
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }

    private function showError(string $message): void
    {
        $content = $this->view->render('error', ['message' => $message]);
        echo $this->view->renderLayout('layout', $content, [
            'title' => 'Error - OxWinches',
            'cartCount' => $this->getCartItemCount(),
            'customer' => $this->getCustomer(),
            'isLoggedIn' => $this->isLoggedIn()
        ]);
    }

    private function handleHealthCheck(): void
    {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'status' => 'healthy',
            'timestamp' => date('c'),
            'service' => 'mia-frontend'
        ]);
        exit;
    }
}