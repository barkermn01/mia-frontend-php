<?php

namespace Marti\Frontend;

use Mia\SDK\MiaClient;
use Mia\SDK\Exceptions\MiaException;
use Mia\SDK\Exceptions\AuthenticationException;
use Marti\Frontend\Controllers\ProductController;
use Marti\Frontend\Controllers\ShippingController;
use Marti\Frontend\Controllers\StockController;
use Marti\Frontend\Controllers\OrderController;
use Marti\Frontend\Controllers\CustomerController;
use Marti\Frontend\Controllers\SiteAdminController;
use Marti\Frontend\Controllers\SettingsController;
use Marti\Frontend\Controllers\DashboardController;
use Marti\Frontend\Controllers\AlertsController;

class AdminRouter
{
    private $client;
    private $cache;
    private $view;
    private $config;
    private $adminPath;
    private $settingsCache;
    
    // Controllers
    private $productController;
    private $shippingController;
    private $stockController;
    private $orderController;
    private $customerController;
    private $siteAdminController;
    private $settingsController;
    private $dashboardController;
    private $alertsController;

    public function __construct()
    {
        $this->loadConfig();
        $this->initializeSession();
        $this->initializeClient();
        $this->initializeCache();
        $this->initializeSettingsCache();
        $this->initializeView();
        $this->initializeControllers();
    }

    private function loadConfig(): void
    {
        // Load .env file if it exists
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }
                
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"\'');
                
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
            'admin_address' => rtrim($_ENV['ADMIN_ADDRESS'] ?? getenv('ADMIN_ADDRESS') ?: 'admin/', '/')
        ];

        $this->adminPath = '/' . $this->config['admin_address'];

        if (!$this->config['site_id']) {
            throw new \Exception('MIA_SITE_ID environment variable is required. Please set it in your .env file.');
        }
    }

    private function initializeSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function initializeClient(): void
    {
        $this->client = new MiaClient([
            'apiUrl' => $this->config['api_url'],
            'siteId' => $this->config['site_id'],
            'verify_ssl' => $this->config['verify_ssl'],
            'debug' => false
        ]);

        if (isset($_SESSION['auth_token'])) {
            $this->client->setAuthToken($_SESSION['auth_token']);
        }
    }

    private function initializeCache(): void
    {
        $this->cache = null;
    }

    private function initializeSettingsCache(): void
    {
        if (!empty($this->config['site_id'])) {
            $this->settingsCache = new SettingsCache($this->config['site_id']);
        }
    }

    private function initializeView(): void
    {
        $this->view = new View(__DIR__ . '/templates/admin');
        HtmlResources::getInstance()->addDefaults();
        
        // Set admin title from settings
        $siteId = $_ENV['MIA_SITE_ID'] ?? getenv('MIA_SITE_ID');
        if ($siteId) {
            $settingsCache = new SettingsCache($siteId);
            $companyName = $settingsCache->get('BRAND:company_name');
            if ($companyName && isset($companyName['value'])) {
                HtmlResources::getInstance()->setTitle('Admin Panel - ' . $companyName['value']);
            } else {
                HtmlResources::getInstance()->setTitle('Admin Panel');
            }
        } else {
            HtmlResources::getInstance()->setTitle('Admin Panel');
        }
    }

    private function initializeControllers(): void
    {
        $this->productController = new ProductController($this->client, $this->view, $this->config, $this->adminPath);
        $this->shippingController = new ShippingController($this->client, $this->view, $this->config, $this->adminPath);
        $this->stockController = new StockController($this->client, $this->view, $this->config, $this->adminPath);
        $this->orderController = new OrderController($this->client, $this->view, $this->config, $this->adminPath);
        $this->customerController = new CustomerController($this->client, $this->view, $this->config, $this->adminPath);
        $this->siteAdminController = new SiteAdminController($this->client, $this->view, $this->config, $this->adminPath);
        $this->settingsController = new SettingsController($this->client, $this->view, $this->config, $this->adminPath);
        $this->dashboardController = new DashboardController($this->client, $this->view, $this->config, $this->adminPath);
        $this->alertsController = new AlertsController($this->client, $this->view, $this->config, $this->adminPath);
    }

    public function handleRequest(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Check if this is an admin request
        if (strpos($path, $this->adminPath) !== 0) {
            return;
        }

        // Handle API routes for AJAX (before auth check for some endpoints)
        if (strpos($path, $this->adminPath . '/api/') === 0) {
            $this->handleApiRequest($path, $method);
            return;
        }

        // Check admin authentication
        if (!$this->isAdminAuthenticated()) {
            $this->redirectToLogin();
            return;
        }

        // Remove admin path prefix to get the actual admin route
        $adminRoute = substr($path, strlen($this->adminPath));
        if ($adminRoute === '') {
            $adminRoute = '/';
        }

        // Handle order details URLs: /admin/orders/{UUID}
        if (preg_match('/^\/orders\/([a-f0-9\-]{36})$/', $adminRoute, $matches)) {
            $this->orderController->showDetails($matches[1]);
            return;
        }

        // Route to appropriate controller
        $this->route($adminRoute, $method);
    }

    private function route(string $route, string $method): void
    {
        switch ($route) {
            case '/':
                $this->dashboardController->index();
                break;
            
            // Product routes
            case '/products':
                $this->productController->index();
                break;
            case '/products/add':
                $method === 'POST' ? $this->productController->handleAdd() : $this->productController->showAdd();
                break;
            case '/products/edit':
                $method === 'POST' ? $this->productController->handleEdit() : $this->productController->showEdit();
                break;
            case '/products/delete':
                $method === 'POST' ? $this->productController->handleDelete() : $this->show404();
                break;
            
            // Orders
            case '/orders':
                $this->orderController->index();
                break;
            
            // Stock
            case '/stock':
                $method === 'POST' ? $this->stockController->handleUpdate() : $this->stockController->index();
                break;
            
            // Customers
            case '/customers':
                $this->customerController->index();
                break;
            
            // Customer Orders - needs to match the route parameter
            case (preg_match('#^/customers/([a-f0-9\-]{36})/orders$#', $route, $matches) ? true : false):
                $_GET['id'] = $matches[1];
                $this->customerController->showOrders();
                break;
            
            // Site Admins
            case '/site-admins':
                $this->siteAdminController->index();
                break;
            case '/site-admins/add':
                $method === 'POST' ? $this->siteAdminController->handleAdd() : $this->siteAdminController->showAdd();
                break;
            case '/site-admins/edit':
                $method === 'POST' ? $this->siteAdminController->handleEdit() : $this->siteAdminController->showEdit();
                break;
            
            // Settings
            case '/settings':
                $method === 'POST' ? $this->settingsController->handleUpdate() : $this->settingsController->index();
                break;
            case '/settings/stripe':
                $this->settingsController->showStripe();
                break;
            
            // Alerts
            case '/alerts':
                $this->alertsController->index();
                break;
            
            // Shipping routes
            case '/shipping':
                $this->shippingController->index();
                break;
            case '/shipping/add':
                $method === 'POST' ? $this->shippingController->handleAdd() : $this->shippingController->showAdd();
                break;
            case '/shipping/edit':
                $method === 'POST' ? $this->shippingController->handleEdit() : $this->shippingController->showEdit();
                break;
            case '/shipping/delete':
                $method === 'POST' ? $this->shippingController->handleDelete() : $this->show404();
                break;
            
            default:
                $this->show404();
                break;
        }
    }

    private function isAdminAuthenticated(): bool
    {
        if (!isset($_SESSION['auth_token']) || !isset($_SESSION['customer'])) {
            return false;
        }

        try {
            $this->client->setAuthToken($_SESSION['auth_token']);
            $authContext = $this->client->getAuthContext();
            
            if (!$authContext || !isset($authContext['user'])) {
                $this->clearUserSession();
                return false;
            }
            
            $user = $authContext['user'];
            $allowedRoles = ['super_admin', 'site_admin'];
            
            if (!isset($user['role']) || !in_array($user['role'], $allowedRoles)) {
                $this->clearUserSession();
                return false;
            }
            
            $_SESSION['customer'] = $user;
            return true;
            
        } catch (\Exception $e) {
            $this->clearUserSession();
            return false;
        }
    }

    private function clearUserSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_start();
    }

    private function redirectToLogin(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_start();
        header("Location: /?error=" . urlencode('You have been logged out due to insufficient admin privileges.'));
        exit;
    }

    private function handleApiRequest(string $path, string $method): void
    {
        header('Content-Type: application/json');
        
        // Extract API route
        $apiRoute = substr($path, strlen($this->adminPath . '/api'));
        
        // Some API endpoints don't require auth
        $publicEndpoints = [];
        
        if (!in_array($apiRoute, $publicEndpoints) && !$this->isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        // Route API requests
        switch ($apiRoute) {
            case '/upload-token':
                if ($method === 'POST') {
                    $this->handleUploadToken();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
                
            case '/upload-image':
                if ($method === 'POST') {
                    $this->handleUploadImage();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
                
            case '/settings/delete':
                if ($method === 'POST') {
                    $this->settingsController->handleDelete();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            // Get single setting
            case (preg_match('/^\/settings\/(.+)$/', $apiRoute, $matches) ? true : false):
                if ($method === 'GET') {
                    try {
                        $settingName = urldecode($matches[1]);
                        
                        // Try cache first
                        $setting = null;
                        if ($this->settingsCache) {
                            $cached = $this->settingsCache->get($settingName);
                            
                            // Cache hit with data
                            if ($cached !== null && $cached !== false) {
                                $setting = $cached;
                            }
                            
                            // Cache hit with "not found" marker
                            if ($cached === false) {
                                echo json_encode(['value' => null]);
                                return;
                            }
                        }
                        
                        // Cache miss - fetch from API
                        if ($setting === null) {
                            $setting = $this->client->siteSettings->getSetting($settingName);
                            
                            // Store in cache
                            if ($this->settingsCache && $setting) {
                                $this->settingsCache->set($settingName, $setting);
                            }
                        }
                        
                        echo json_encode($setting);
                    } catch (\Exception $e) {
                        // Setting doesn't exist - cache the "not found" result
                        if ($this->settingsCache) {
                            $this->settingsCache->setNotFound($settingName);
                        }
                        
                        echo json_encode(['value' => null]);
                    }
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            // Alert API routes
            case (preg_match('/^\/alerts\/([a-f0-9\-]{36})\/([a-f0-9\-]{36})\/read$/', $apiRoute, $matches) ? true : false):
                if ($method === 'PATCH') {
                    $this->alertsController->markAsRead($matches[1], $matches[2]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case '/alerts/read-all':
                if ($method === 'PATCH') {
                    $this->alertsController->markAllAsRead();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case (preg_match('/^\/alerts\/([a-f0-9\-]{36})\/([a-f0-9\-]{36})$/', $apiRoute, $matches) ? true : false):
                if ($method === 'DELETE') {
                    $this->alertsController->delete($matches[1], $matches[2]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            // Order progression API routes
            case '/orders/process':
                if ($method === 'POST') {
                    $this->orderController->handleProcess();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case '/orders/cancel':
                if ($method === 'POST') {
                    $this->orderController->handleCancel();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case '/orders/ship':
                if ($method === 'POST') {
                    $this->orderController->handleShip();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case '/orders/complete':
                if ($method === 'POST') {
                    $this->orderController->handleComplete();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case '/orders/update-status':
                if ($method === 'POST') {
                    $this->orderController->handleUpdateStatus();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case '/orders/refund':
                if ($method === 'POST') {
                    $this->orderController->handleRefund();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case '/orders/partial-refund':
                if ($method === 'POST') {
                    $this->orderController->handlePartialRefund();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            // Customer API routes
            case (preg_match('/^\/customers\/([a-f0-9\-]{36})$/', $apiRoute, $matches) ? true : false):
                $_GET['id'] = $matches[1];
                $this->customerController->apiGetCustomer();
                break;
            
            case (preg_match('/^\/customers\/([a-f0-9\-]{36})\/status$/', $apiRoute, $matches) ? true : false):
                if ($method === 'POST') {
                    $_GET['id'] = $matches[1];
                    $this->customerController->apiUpdateStatus();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case (preg_match('/^\/customers\/([a-f0-9\-]{36})\/archive$/', $apiRoute, $matches) ? true : false):
                if ($method === 'POST') {
                    $_GET['id'] = $matches[1];
                    $this->customerController->apiArchiveCustomer();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            
            case (preg_match('/^\/customers\/([a-f0-9\-]{36})\/unarchive$/', $apiRoute, $matches) ? true : false):
                if ($method === 'POST') {
                    $_GET['id'] = $matches[1];
                    $this->customerController->apiUnarchiveCustomer();
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'API endpoint not found']);
                break;
        }
    }
    
    private function handleUploadToken(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['filename']) || !isset($input['contentType'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $result = $this->client->s3->generateUploadToken(
                $input['filename'],
                $input['contentType'],
                $input['maxSizeBytes'] ?? 5242880
            );
            
            echo json_encode(['data' => $result]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function handleUploadImage(): void
    {
        try {
            if (!isset($_FILES['image']) || !isset($_POST['uploadToken'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $file = $_FILES['image'];
            $uploadToken = $_POST['uploadToken'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'File upload error']);
                return;
            }
            
            $result = $this->client->s3->uploadImage(
                $uploadToken,
                $file['tmp_name']
            );
            
            echo json_encode(['data' => $result]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function show404(): void
    {
        http_response_code(404);
        $content = $this->view->render('404');
        echo $this->view->renderLayout('admin-layout', $content, [
            'title' => 'Page Not Found - Admin Panel',
            'user' => $_SESSION['customer'],
            'adminPath' => $this->adminPath
        ]);
    }
}
