<?php

namespace Marti\Frontend;

use Mia\SDK\MiaClient;
use Mia\SDK\Exceptions\MiaException;
use Mia\SDK\Exceptions\AuthenticationException;

class Admin
{
    private $client;
    private $cache;
    private $view;
    private $config;
    private $adminPath;

    public function __construct()
    {
        $this->loadConfig();
        $this->initializeSession();
        $this->initializeClient();
        $this->initializeCache();
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

        // Set auth token if user is logged in
        if (isset($_SESSION['auth_token'])) {
            $this->client->setAuthToken($_SESSION['auth_token']);
        }
    }

    private function initializeCache(): void
    {
        // No external cache dependency - use simple in-memory cache or no cache
        $this->cache = null;
    }

    private function initializeView(): void
    {
        $this->view = new View(__DIR__ . '/templates/admin');
        
        // Initialize HTML resources with admin defaults
        HtmlResources::getInstance()->addDefaults();
        HtmlResources::getInstance()->setTitle('Admin Panel - OxWinches');
    }

    public function handleRequest(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        

        
        // Check if this is an admin request
        if (strpos($path, $this->adminPath) !== 0) {
            return; // Not an admin request
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


        switch ($adminRoute) {
            case '/':
                $this->showDashboard();
                break;
            case '/products':
                $this->showProducts();
                break;
            case '/products/add':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->handleAddProduct();
                } else {
                    $this->showAddProduct();
                }
                break;
            case '/products/edit':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->handleEditProduct();
                } else {
                    $this->showEditProduct();
                }
                break;
            case '/products/delete':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->handleDeleteProduct();
                } else {
                    $this->show404();
                }
                break;
            case '/orders':
                $this->showOrders();
                break;
            case '/customers':
                $this->showCustomers();
                break;
            case '/site-admins':
                $this->showSiteAdmins();
                break;
            case '/site-admins/add':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->handleAddSiteAdmin();
                } else {
                    $this->showAddSiteAdmin();
                }
                break;
            case '/site-admins/edit':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $this->handleEditSiteAdmin();
                } else {
                    $this->showEditSiteAdmin();
                }
                break;
            case '/settings':
                $this->showSettings();
                break;
            default:
                $this->show404();
        }
    }

    private function handleApiRequest(string $path, string $method): void
    {
        header('Content-Type: application/json');
        
        // Check admin authentication for API requests
        if (!$this->isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        try {
            $adminApiRoute = substr($path, strlen($this->adminPath . '/api'));
            
            switch ($adminApiRoute) {
                case '/upload-token':
                    if ($method === 'POST') {
                        $this->apiGenerateUploadToken();
                    }
                    break;
                case '/upload-image':
                    if ($method === 'POST') {
                        $this->apiUploadImage();
                    }
                    break;
                case '/stock/variants':
                    if ($method === 'GET') {
                        $this->apiGetProductVariantsStock();
                    }
                    break;
                case '/stock/adjust':
                    if ($method === 'POST') {
                        $this->apiAdjustStock();
                    }
                    break;
                case '/site-admins':
                    if ($method === 'GET') {
                        $this->apiGetSiteAdmins();
                    } elseif ($method === 'POST') {
                        $this->apiCreateSiteAdmin();
                    }
                    break;
                case '/site-admins/delete':
                    if ($method === 'POST') {
                        $this->apiDeleteSiteAdmin();
                    }
                    break;
                default:
                    // Handle customer API routes
                    if (preg_match('/^\/customers\/([^\/]+)$/', $adminApiRoute, $matches)) {
                        if ($method === 'GET') {
                            $this->apiGetCustomer($matches[1]);
                        }
                    } elseif (preg_match('/^\/customers\/([^\/]+)\/status$/', $adminApiRoute, $matches)) {
                        if ($method === 'POST') {
                            $this->apiUpdateCustomerStatus($matches[1]);
                        }
                    } else {
                        http_response_code(404);
                        echo json_encode(['error' => 'API endpoint not found']);
                    }
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function apiGenerateUploadToken(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $filename = $input['filename'] ?? '';
        $contentType = $input['contentType'] ?? '';
        $maxSizeBytes = $input['maxSizeBytes'] ?? 5242880; // 5MB default
        
        if (!$filename || !$contentType) {
            http_response_code(400);
            echo json_encode(['error' => 'Filename and content type are required']);
            return;
        }

        try {
            $result = $this->client->s3->generateUploadToken($filename, $contentType, $maxSizeBytes);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (MiaException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    private function apiUploadImage(): void
    {
        error_log("apiUploadImage called");
        error_log("FILES data: " . json_encode($_FILES));
        error_log("POST data keys: " . implode(', ', array_keys($_POST)));
        
        if (!isset($_FILES['image']) || !isset($_POST['uploadToken'])) {
            error_log("Missing required data - image file: " . (isset($_FILES['image']) ? 'YES' : 'NO') . ", uploadToken: " . (isset($_POST['uploadToken']) ? 'YES' : 'NO'));
            http_response_code(400);
            echo json_encode(['error' => 'Image file and upload token are required']);
            return;
        }

        $uploadToken = $_POST['uploadToken'];
        $uploadedFile = $_FILES['image'];
        
        error_log("Upload token: " . substr($uploadToken, 0, 50) . "...");
        error_log("Uploaded file error code: " . $uploadedFile['error']);
        error_log("Uploaded file size: " . $uploadedFile['size']);
        error_log("Uploaded file name: " . $uploadedFile['name']);
        
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            error_log("File upload error detected: " . $uploadedFile['error']);
            http_response_code(400);
            echo json_encode(['error' => 'File upload error: ' . $uploadedFile['error']]);
            return;
        }

        try {
            error_log("Calling s3->uploadImage...");
            $result = $this->client->s3->uploadImage($uploadToken, $uploadedFile['tmp_name']);
            error_log("Image upload successful: " . json_encode($result));
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (MiaException $e) {
            error_log("Image upload failed: " . $e->getMessage());
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log("Unexpected error in image upload: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    private function isAdminAuthenticated(): bool
    {
        if (!isset($_SESSION['auth_token']) || !isset($_SESSION['customer'])) {
            return false;
        }

        // Validate token and permissions with the API on every request
        try {
            // Set the auth token on the client
            $this->client->setAuthToken($_SESSION['auth_token']);
            
            // Validate the token and get current user context from API
            $authContext = $this->client->getAuthContext();
            
            if (!$authContext || !isset($authContext['user'])) {
                // Clear ALL session data for invalid token
                $this->clearUserSession();
                return false;
            }
            
            $user = $authContext['user'];
            $allowedRoles = ['super_admin', 'site_admin'];
            
            // Check if user has admin role
            if (!isset($user['role']) || !in_array($user['role'], $allowedRoles)) {
                // Clear ALL session data for non-admin users
                $this->clearUserSession();
                return false;
            }
            
            // Update session with fresh user data from API
            $_SESSION['customer'] = $user;
            
            return true;
            
        } catch (\Exception $e) {
            // Clear ALL session data for API errors
            $this->clearUserSession();
            return false;
        }
    }

    private function clearUserSession(): void
    {
        // Completely destroy the session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Start a completely fresh session
        session_start();
    }

    private function redirectToLogin(): void
    {
        // Completely destroy the session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Start a completely fresh session
        session_start();
        
        // Redirect to homepage with clear message about logout
        header("Location: /?error=" . urlencode('You have been logged out due to insufficient admin privileges.'));
        exit;
    }

    private function showDashboard(): void
    {
        try {
            // Get dashboard data
            $stats = [
                'total_products' => 0,
                'total_orders' => 0,
                'total_customers' => 0,
                'revenue' => 0
            ];

            // Try to get actual stats from API
            try {
                $products = $this->client->products->getProducts(['limit' => 1]);
                $stats['total_products'] = $products['total'] ?? 0;
            } catch (MiaException $e) {
                // Failed to get products count
            }

            try {
                $orders = $this->client->orders->getOrders(['limit' => 1]);
                $stats['total_orders'] = $orders['total'] ?? 0;
            } catch (MiaException $e) {
                // Failed to get orders count
            }

            $content = $this->view->render('dashboard', [
                'stats' => $stats,
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Dashboard - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (\Exception $e) {
            $this->showError("Failed to load dashboard: " . $e->getMessage());
        }
    }

    private function showProducts(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            
            $filters = [
                'page' => $page,
                'limit' => 20
            ];
            
            if ($search) {
                $filters['search'] = $search;
            }

            $products = $this->client->products->getProducts($filters);
            
            // Fetch variants for each product
            $productsWithVariants = [];
            foreach ($products['items'] ?? [] as $product) {
                try {
                    $variants = $this->client->products->getProductVariants($product['id']);
                    $product['variants'] = $variants['items'] ?? [];
                } catch (MiaException $e) {
                    $product['variants'] = [];
                }
                $productsWithVariants[] = $product;
            }
            
            $content = $this->view->render('products', [
                'products' => $productsWithVariants,
                'total' => $products['total'] ?? 0,
                'page' => $page,
                'search' => $search,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Products - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load products: " . $e->getMessage());
        }
    }

    private function showAddProduct(): void
    {
        try {
            // Add Toast UI Editor resources
            $htmlResources = \Marti\Frontend\HtmlResources::getInstance();
            $htmlResources->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            $htmlResources->addCss('/css/markdown.css'); // Add our custom markdown styles
            $htmlResources->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            $content = $this->view->render('product-form', [
                'product' => null,
                'isEdit' => false,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Add Product - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (\Exception $e) {
            $this->showError("Failed to load add product form: " . $e->getMessage());
        }
    }

    private function showEditProduct(): void
    {
        $productId = $_GET['id'] ?? '';
        if (!$productId) {
            $this->showError("Product ID is required");
            return;
        }

        try {
            // Add Toast UI Editor resources
            $htmlResources = \Marti\Frontend\HtmlResources::getInstance();
            $htmlResources->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            $htmlResources->addCss('/css/markdown.css'); // Add our custom markdown styles
            $htmlResources->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            $product = $this->client->products->getProduct($productId);
            $variants = $this->client->products->getProductVariants($productId);
            
            $content = $this->view->render('product-form', [
                'product' => $product,
                'variants' => $variants['items'] ?? [],
                'isEdit' => true,
                'adminPath' => $this->adminPath
            ]);
            
            $output = $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Edit Product - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
            
            echo $output;
            
        } catch (MiaException $e) {
            $this->showError("Failed to load product: " . $e->getMessage() . " (This may indicate insufficient API permissions for product ID: {$productId})");
        } catch (\Exception $e) {
            $this->showError("An error occurred while loading the product edit form: " . $e->getMessage());
        }
    }

    private function generateSlug(string $title): string
    {
        // Convert to lowercase and replace non-alphanumeric characters with hyphens
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Add timestamp to ensure uniqueness
        $timestamp = date('ymd-His');
        return $slug . '-' . $timestamp;
    }

    private function handleAddProduct(): void
    {
        try {
            $title = $_POST['title'] ?? '';
            
            $data = [
                'title' => $title,
                'slug' => $this->generateSlug($title),
                'shortDescription' => $_POST['short_description'] ?? '',
                'description' => $_POST['description'] ?? '',
                'categories' => !empty($_POST['category']) ? array_map('trim', explode(',', $_POST['category'])) : [],
                'tags' => !empty($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : [],
                'status' => $_POST['status'] ?? 'active'
            ];

            // Validate required fields
            if (empty($data['title'])) {
                throw new \Exception('Product title is required');
            }
            
            if (empty($data['shortDescription'])) {
                throw new \Exception('Short description is required');
            }

            // Handle images
            $images = [];
            if (!empty($_POST['images'])) {
                $imageUrls = json_decode($_POST['images'], true);
                if (is_array($imageUrls)) {
                    foreach ($imageUrls as $imageUrl) {
                        $images[] = [
                            'url' => $imageUrl,
                            'alt' => $data['title']
                        ];
                    }
                }
            }
            
            if (!empty($images)) {
                $data['images'] = $images;
            }

            // Handle variants
            $variants = [];
            
            if (!empty($_POST['variants'])) {
                $variantData = json_decode($_POST['variants'], true);
                if (is_array($variantData)) {
                    foreach ($variantData as $variant) {
                        if (!empty($variant['sku']) && !empty($variant['price'])) {
                            $processedVariant = [
                                'sku' => $variant['sku'],
                                'price' => round($variant['price'] * 100), // Convert to pence/cents
                                'attributes' => $variant['attributes'] ?? []
                            ];
                            
                            // Add presentable name if provided
                            if (!empty($variant['presentableName'])) {
                                $processedVariant['presentableName'] = $variant['presentableName'];
                            }
                            
                            $variants[] = $processedVariant;
                        }
                    }
                }
            }
            
            if (empty($variants)) {
                // Don't include variants in product data
            } else {
                // Don't include variants in product creation - handle separately
            }

            $result = $this->client->products->createProduct($data);
            
            // Handle variants separately if we have any
            if (!empty($variants) && isset($result['id'])) {
                $createdProductId = $result['id'];
                foreach ($variants as $variant) {
                    try {
                        $variantResult = $this->client->products->createVariant($createdProductId, $variant);
                    } catch (\Exception $e) {
                        // Failed to create variant
                    }
                }
            }
            
            header("Location: {$this->adminPath}/products?success=" . urlencode('Product created successfully'));
            exit;
        } catch (\Exception $e) {
            
            // Add Toast UI Editor resources for error display
            $htmlResources = \Marti\Frontend\HtmlResources::getInstance();
            $htmlResources->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            $htmlResources->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            // Preserve form data for redisplay
            $formData = $_POST;
            
            // Ensure tags are properly formatted for display
            if (isset($formData['tags']) && is_string($formData['tags'])) {
                // Tags are already a string from the form, keep as is
                $formData['tags'] = $formData['tags'];
            }
            
            // Preserve uploaded images
            $uploadedImages = [];
            if (!empty($_POST['images'])) {
                $imageUrls = json_decode($_POST['images'], true);
                if (is_array($imageUrls)) {
                    foreach ($imageUrls as $imageUrl) {
                        $uploadedImages[] = ['url' => $imageUrl];
                    }
                }
            }
            $formData['images'] = $uploadedImages;
            
            // Preserve variants
            $variants = [];
            if (!empty($_POST['variants'])) {
                $variantData = json_decode($_POST['variants'], true);
                if (is_array($variantData)) {
                    $variants = $variantData;
                }
            }
            
            $content = $this->view->render('product-form', [
                'product' => $formData,
                'variants' => $variants,
                'isEdit' => false,
                'error' => $e->getMessage(),
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Add Product - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        }
    }

    private function handleEditProduct(): void
    {
        $productId = $_POST['product_id'] ?? '';
        if (!$productId) {
            $this->showError("Product ID is required");
            return;
        }

        try {
            $data = [
                'title' => $_POST['title'] ?? '',
                'shortDescription' => $_POST['short_description'] ?? '',
                'description' => $_POST['description'] ?? '',
                'categories' => !empty($_POST['category']) ? array_map('trim', explode(',', $_POST['category'])) : [],
                'tags' => !empty($_POST['tags']) ? array_map('trim', explode(',', $_POST['tags'])) : [],
                'status' => $_POST['status'] ?? 'active'
            ];

            // Validate required fields
            if (empty($data['title'])) {
                throw new \Exception('Product title is required');
            }
            
            if (empty($data['shortDescription'])) {
                throw new \Exception('Short description is required');
            }

            // Handle images
            $images = [];
            if (!empty($_POST['images'])) {
                $imageUrls = json_decode($_POST['images'], true);
                if (is_array($imageUrls)) {
                    foreach ($imageUrls as $imageUrl) {
                        $images[] = [
                            'url' => $imageUrl,
                            'alt' => $data['title']
                        ];
                    }
                }
            }
            
            if (!empty($images)) {
                $data['images'] = $images;
            }

            // Handle variants
            $variants = [];
            if (!empty($_POST['variants'])) {
                $variantData = json_decode($_POST['variants'], true);
                error_log("Raw variant data from form: " . print_r($variantData, true));
                
                if (is_array($variantData)) {
                    foreach ($variantData as $variant) {
                        if (!empty($variant['sku']) && !empty($variant['price'])) {
                            $processedVariant = [
                                'sku' => $variant['sku'],
                                'price' => round($variant['price'] * 100), // Convert to pence/cents
                                'attributes' => $variant['attributes'] ?? []
                            ];
                            
                            // Add UUID if it exists (for existing variants)
                            if (!empty($variant['uuid'])) {
                                $processedVariant['uuid'] = $variant['uuid'];
                                error_log("Processing EXISTING variant: {$variant['sku']} with UUID: {$variant['uuid']}");
                            } else {
                                error_log("Processing NEW variant: {$variant['sku']} (no UUID)");
                            }
                            
                            // Add presentable name if provided
                            if (!empty($variant['presentableName'])) {
                                $processedVariant['presentableName'] = $variant['presentableName'];
                            }
                            
                            $variants[] = $processedVariant;
                        }
                    }
                }
            }

            $result = $this->client->products->updateProduct($productId, $data);
            
            // Handle variants separately if we have any
            if (!empty($variants)) {
                try {
                    $existingVariants = $this->client->products->getProductVariants($productId);
                    
                    // Create a map of existing variants by UUID
                    $existingVariantsByUuid = [];
                    $existingVariantsBySku = [];
                    if (!empty($existingVariants)) {
                        $variantsList = $existingVariants;
                        if (isset($existingVariants['items'])) {
                            $variantsList = $existingVariants['items'];
                        }
                        
                        foreach ($variantsList as $existingVariant) {
                            $variantId = $existingVariant['uuid'] ?? $existingVariant['id'] ?? null;
                            if ($variantId) {
                                $existingVariantsByUuid[$variantId] = $existingVariant;
                            }
                            if (isset($existingVariant['sku'])) {
                                $existingVariantsBySku[$existingVariant['sku']] = $existingVariant;
                            }
                        }
                    }
                    
                    // Track which existing variants are being kept
                    $keptVariantUuids = [];
                    
                    // Process each variant from the form
                    foreach ($variants as $variant) {
                        try {
                            $sku = $variant['sku'];
                            $uuid = $variant['uuid'] ?? null;
                            
                            if ($uuid && isset($existingVariantsByUuid[$uuid])) {
                                // Update existing variant by UUID
                                $this->client->products->updateVariant($productId, $uuid, $variant);
                                $keptVariantUuids[] = $uuid;
                            } elseif (isset($existingVariantsBySku[$sku])) {
                                // Update existing variant by SKU (fallback)
                                $existingVariant = $existingVariantsBySku[$sku];
                                $existingVariantId = $existingVariant['uuid'] ?? $existingVariant['id'] ?? null;
                                
                                if ($existingVariantId) {
                                    $this->client->products->updateVariant($productId, $existingVariantId, $variant);
                                    $keptVariantUuids[] = $existingVariantId;
                                }
                            } else {
                                // Create new variant
                                $newVariant = $this->client->products->createVariant($productId, $variant);
                                // Track the new variant ID if returned
                                if (isset($newVariant['uuid']) || isset($newVariant['id'])) {
                                    $keptVariantUuids[] = $newVariant['uuid'] ?? $newVariant['id'];
                                }
                            }
                        } catch (\Exception $e) {
                            // Log variant processing errors but continue
                            error_log("Failed to process variant {$variant['sku']}: " . $e->getMessage());
                        }
                    }
                    
                    // Delete variants that were removed from the form
                    foreach ($existingVariantsByUuid as $existingUuid => $existingVariant) {
                        if (!in_array($existingUuid, $keptVariantUuids)) {
                            try {
                                $this->client->products->deleteVariant($productId, $existingUuid);
                                error_log("Deleted variant with UUID: {$existingUuid}");
                            } catch (\Exception $e) {
                                error_log("Failed to delete variant {$existingUuid}: " . $e->getMessage());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Log variant management errors but continue
                    error_log("Failed to manage variants: " . $e->getMessage());
                }
            }
            
            // Redirect to products list with success message
            $imageCount = count($images);
            $successMessage = "Product updated successfully. Images: {$imageCount}";
            
            header("Location: {$this->adminPath}/products?success=" . urlencode($successMessage));
            exit;
        } catch (\Exception $e) {
            // Add Toast UI Editor resources for error display
            $htmlResources = \Marti\Frontend\HtmlResources::getInstance();
            $htmlResources->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            $htmlResources->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            // Preserve form data for redisplay
            $formData = $_POST;
            
            // Ensure tags are properly formatted for display
            if (isset($formData['tags']) && is_string($formData['tags'])) {
                // Tags are already a string from the form, keep as is
                $formData['tags'] = $formData['tags'];
            }
            
            // Preserve uploaded images
            $uploadedImages = [];
            if (!empty($_POST['images'])) {
                $imageUrls = json_decode($_POST['images'], true);
                if (is_array($imageUrls)) {
                    foreach ($imageUrls as $imageUrl) {
                        $uploadedImages[] = [
                            'url' => $imageUrl,
                            'alt' => $formData['title'] ?? 'Product image'
                        ];
                    }
                }
            }
            $formData['images'] = $uploadedImages;
            
            // Preserve variants
            $variants = [];
            if (!empty($_POST['variants'])) {
                $variants = json_decode($_POST['variants'], true) ?: [];
            }
            
            $content = $this->view->render('product-form', [
                'product' => $formData,
                'error' => $e->getMessage(),
                'variants' => $variants,
                'isEdit' => true,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Edit Product - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        }
    }

    private function apiGetCustomer(string $customerId): void
    {
        try {
            // Use customer service admin endpoint for customer details
            $customer = $this->client->customer->getCustomer($customerId);
            echo json_encode(['success' => true, 'customer' => $customer]);
        } catch (MiaException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function apiUpdateCustomerStatus(string $customerId): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $status = $input['status'] ?? '';
            
            if (!in_array($status, ['active', 'inactive'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid status. Must be active or inactive.']);
                return;
            }

            // Use customer service admin endpoint for customer status updates
            $result = $this->client->customer->updateCustomerStatus($customerId, $status);
            echo json_encode(['success' => true, 'message' => 'Customer status updated successfully']);
        } catch (MiaException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function handleDeleteProduct(): void
    {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $productId = $input['productId'] ?? '';
            
            if (!$productId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Product ID is required']);
                return;
            }

            // Call the API to delete the product
            $result = $this->client->products->deleteProduct($productId);
            
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
        } catch (\Exception $e) {
            error_log("Product deletion error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function showOrders(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            
            $filters = [
                'page' => $page,
                'limit' => 20
            ];

            $orders = $this->client->orders->getOrders($filters);
            
            $content = $this->view->render('orders', [
                'orders' => $orders['items'] ?? [],
                'total' => $orders['total'] ?? 0,
                'page' => $page
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Orders - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load orders: " . $e->getMessage());
        }
    }

    private function showCustomers(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            
            $params = [
                'page' => $page,
                'limit' => 20
            ];
            
            if ($search) {
                $params['search'] = $search;
            }

            // Debug: Log the request
            error_log("showCustomers - Fetching customers with params: " . json_encode($params));

            // Use admin API endpoint for customer management
            $customers = $this->client->customer->listCustomers($params);
            
            // Debug: Log the response
            error_log("showCustomers - Response: " . json_encode($customers));
            
            $content = $this->view->render('customers', [
                'customers' => $customers['customers'] ?? [],
                'total' => $customers['pagination']['total'] ?? 0,
                'page' => $page,
                'search' => $search,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Customers - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            error_log("showCustomers - Error: " . $e->getMessage());
            $this->showError("Failed to load customers: " . $e->getMessage());
        }
    }

    private function showSettings(): void
    {
        try {
            $content = $this->view->render('settings', [
                'config' => $this->config
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Settings - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load settings: " . $e->getMessage());
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

    private function showError(string $message): void
    {
        $content = $this->view->render('error', ['message' => $message]);
        echo $this->view->renderLayout('admin-layout', $content, [
            'title' => 'Error - Admin Panel',
            'user' => $_SESSION['customer'],
            'adminPath' => $this->adminPath
        ]);
    }

    private function apiGetProductVariantsStock(): void
    {
        $productId = $_GET['productId'] ?? '';
        
        if (!$productId) {
            http_response_code(400);
            echo json_encode(['error' => 'Product ID is required']);
            return;
        }

        try {
            // Get product variants
            $variants = $this->client->products->getProductVariants($productId);
            
            if (!isset($variants['items'])) {
                throw new \Exception('No variants found for product');
            }

            // Get stock information for each variant
            $variantsWithStock = [];
            foreach ($variants['items'] as $variant) {
                try {
                    $stockInfo = $this->client->stock->getStock($variant['sku']);
                    $variant['stockInfo'] = $stockInfo;
                } catch (\Exception $e) {
                    // If stock info fails, use default values
                    $variant['stockInfo'] = [
                        'available' => $variant['stock']['available'] ?? 0,
                        'reserved' => $variant['stock']['reserved'] ?? 0,
                        'allocated' => $variant['stock']['allocated'] ?? 0,
                        'unlimited' => $variant['stock']['unlimited'] ?? false,
                        'inventoryType' => $variant['stock']['inventoryType'] ?? 'physical'
                    ];
                }
                $variantsWithStock[] = $variant;
            }

            echo json_encode([
                'success' => true,
                'variants' => $variantsWithStock
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get stock information: ' . $e->getMessage()]);
        }
    }

    private function apiAdjustStock(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['sku']) || !isset($input['adjustment'])) {
            http_response_code(400);
            echo json_encode(['error' => 'SKU and adjustment amount are required']);
            return;
        }

        $sku = $input['sku'];
        $adjustment = (int)$input['adjustment'];
        $reason = $input['reason'] ?? 'Manual adjustment via admin panel';

        if ($adjustment === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Adjustment amount cannot be zero']);
            return;
        }

        try {
            $result = $this->client->stock->adjustStock([
                'sku' => $sku,
                'delta' => $adjustment,
                'reason' => $reason
            ]);

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to adjust stock: ' . $e->getMessage()]);
        }
    }

    // ==================== SITE ADMIN MANAGEMENT ====================

    private function showSiteAdmins(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            
            $filters = [
                'page' => $page,
                'limit' => 20
            ];
            
            if ($search) {
                $filters['search'] = $search;
            }

            // Get site admins using the admin service
            $admins = $this->client->admin->getUsers($filters);
            
            $content = $this->view->render('site-admins', [
                'admins' => $admins['items'] ?? $admins['data'] ?? [],
                'total' => $admins['total'] ?? $admins['count'] ?? 0,
                'page' => $page,
                'search' => $search,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Site Admins - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (\Exception $e) {
            $this->showError("Failed to load site admins: " . $e->getMessage());
        }
    }

    private function showAddSiteAdmin(): void
    {
        try {
            $content = $this->view->render('site-admin-form', [
                'admin' => null,
                'isEdit' => false,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Add Site Admin - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (\Exception $e) {
            $this->showError("Failed to load add site admin form: " . $e->getMessage());
        }
    }

    private function showEditSiteAdmin(): void
    {
        $adminId = $_GET['id'] ?? '';
        if (!$adminId) {
            $this->showError("Admin ID is required");
            return;
        }

        try {
            $admin = $this->client->admin->getUser($adminId);
            
            $content = $this->view->render('site-admin-form', [
                'admin' => $admin,
                'isEdit' => true,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Edit Site Admin - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (\Exception $e) {
            $this->showError("Failed to load site admin: " . $e->getMessage());
        }
    }

    private function handleAddSiteAdmin(): void
    {
        try {
            $data = [
                'email' => $_POST['email'] ?? '',
                'firstName' => $_POST['firstName'] ?? '',
                'lastName' => $_POST['lastName'] ?? '',
                'role' => $_POST['role'] ?? 'site_admin',
                'password' => $_POST['password'] ?? '',
                'status' => $_POST['status'] ?? 'active'
            ];

            // Validate required fields
            if (empty($data['email'])) {
                throw new \Exception('Email is required');
            }
            
            if (empty($data['firstName'])) {
                throw new \Exception('First name is required');
            }

            if (empty($data['lastName'])) {
                throw new \Exception('Last name is required');
            }

            if (empty($data['password'])) {
                throw new \Exception('Password is required');
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Please enter a valid email address');
            }

            $result = $this->client->admin->createUser($data);
            
            header("Location: {$this->adminPath}/site-admins?success=" . urlencode('Site admin created successfully'));
            exit;
        } catch (\Exception $e) {
            // Preserve form data for redisplay
            $formData = $_POST;
            
            $content = $this->view->render('site-admin-form', [
                'admin' => $formData,
                'isEdit' => false,
                'error' => $e->getMessage(),
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Add Site Admin - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        }
    }

    private function handleEditSiteAdmin(): void
    {
        $adminId = $_POST['admin_id'] ?? '';
        
        // Debug: Log what we received from the form
        error_log("handleEditSiteAdmin - POST data: " . json_encode($_POST));
        error_log("handleEditSiteAdmin - admin_id: " . $adminId);
        
        if (!$adminId) {
            $this->showError("Admin ID is required");
            return;
        }

        try {
            $data = [
                'email' => $_POST['email'] ?? '',
                'firstName' => $_POST['firstName'] ?? '',
                'lastName' => $_POST['lastName'] ?? '',
                'role' => $_POST['role'] ?? 'site_admin',
                'status' => $_POST['status'] ?? 'active'
            ];

            // Only include password if provided
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }

            // Debug: Log what we're sending to the API
            error_log("handleEditSiteAdmin - Data being sent to API: " . json_encode($data));

            // Validate required fields
            if (empty($data['email'])) {
                throw new \Exception('Email is required');
            }
            
            if (empty($data['firstName'])) {
                throw new \Exception('First name is required');
            }

            if (empty($data['lastName'])) {
                throw new \Exception('Last name is required');
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Please enter a valid email address');
            }

            $result = $this->client->admin->updateUser($adminId, $data);
            
            // Debug: Log the API response
            error_log("handleEditSiteAdmin - API response: " . json_encode($result));
            
            header("Location: {$this->adminPath}/site-admins?success=" . urlencode('Site admin updated successfully'));
            exit;
        } catch (\Exception $e) {
            // Preserve form data for redisplay
            $formData = $_POST;
            $formData['id'] = $adminId;
            
            $content = $this->view->render('site-admin-form', [
                'admin' => $formData,
                'isEdit' => true,
                'error' => $e->getMessage(),
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Edit Site Admin - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        }
    }

    // ==================== SITE ADMIN API ENDPOINTS ====================

    private function apiGetSiteAdmins(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            
            $filters = [
                'page' => $page,
                'limit' => 20
            ];
            
            if ($search) {
                $filters['search'] = $search;
            }

            $admins = $this->client->admin->getUsers($filters);
            
            echo json_encode([
                'success' => true,
                'data' => $admins
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get site admins: ' . $e->getMessage()]);
        }
    }

    private function apiCreateSiteAdmin(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['email']) || !isset($input['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and name are required']);
            return;
        }

        try {
            $data = [
                'email' => $input['email'],
                'firstName' => $input['firstName'] ?? '',
                'lastName' => $input['lastName'] ?? '',
                'role' => $input['role'] ?? 'site_admin',
                'password' => $input['password'] ?? '',
                'status' => $input['status'] ?? 'active'
            ];

            if (empty($data['password'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Password is required']);
                return;
            }

            $result = $this->client->admin->createUser($data);

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create site admin: ' . $e->getMessage()]);
        }
    }

    private function apiDeleteSiteAdmin(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['adminId'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Admin ID is required']);
            return;
        }

        $adminId = $input['adminId'];

        try {
            $result = $this->client->admin->deleteUser($adminId);

            echo json_encode([
                'success' => true,
                'message' => 'Site admin deleted successfully'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete site admin: ' . $e->getMessage()]);
        }
    }
}