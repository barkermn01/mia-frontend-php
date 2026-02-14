<?php

namespace Marti\Frontend\Controllers\Frontend;

use Mia\SDK\Exceptions\MiaException;

class AccountController extends BaseController
{
    public function showLogin(): void
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

    public function handleLogin(): void
    {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!$email || !$password) {
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
            $result = $this->client->login([
                'email' => $email,
                'password' => $password,
                'siteId' => getenv('MIA_SITE_ID')
            ]);
            
            $_SESSION['auth_token'] = $result['token'];
            $_SESSION['customer'] = $result['user'];
            $this->client->setAuthToken($result['token']);
            
            // Check if there's a redirect after login
            $redirectTo = $_SESSION['redirect_after_login'] ?? '/account';
            unset($_SESSION['redirect_after_login']);
            
            $this->redirect($redirectTo);
        } catch (\Exception $e) {
            error_log("Login failed: " . $e->getMessage());
            $content = $this->view->render('login', ['error' => 'Invalid email or password']);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Login - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
        }
    }

    public function showRegister(): void
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

    public function handleRegister(): void
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
        } catch (\Exception $e) {
            $content = $this->view->render('register', ['error' => 'Registration failed: ' . $e->getMessage()]);
            echo $this->view->renderLayout('layout', $content, [
                'title' => 'Register - OxWinches',
                'cartCount' => $this->getCartItemCount(),
                'customer' => null,
                'isLoggedIn' => false
            ]);
        }
    }

    public function handleLogout(): void
    {
        unset($_SESSION['auth_token']);
        unset($_SESSION['customer']);
        // Don't call client logout or destroy session - keep cart alive
        $this->redirect('/');
    }

    public function showAccount(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
            return;
        }
        
        try {
            $customer = $this->getCustomer();
            
            // Check if this is a super_admin or other admin account
            if (isset($customer['role']) && in_array($customer['role'], ['super_admin', 'site_admin'])) {
                
                // For admin accounts, we'll show basic account info and admin functions
                $profile = [
                    'id' => $customer['id'],
                    'email' => $customer['email'],
                    'role' => $customer['role'],
                    'status' => $customer['status'] ?? 'active'
                ];
                
                $savedBaskets = []; // Admin accounts don't have saved baskets
                
            } else {
                // For regular customers, get the full profile
                $profile = $this->client->customer->getProfile();
                
                try {
                    $savedBasketsResponse = $this->client->cart->getSavedBaskets();
                    $savedBaskets = $savedBasketsResponse['items'] ?? [];
                } catch (\Exception $e) {
                    error_log("Failed to fetch saved baskets: " . $e->getMessage());
                    $savedBaskets = [];
                }
            }
            
            // Add account.js for edit profile and address functionality
            \Marti\Frontend\HtmlResources::getInstance()->addJsBody('/js/account.js');
            
            $this->renderLayout('account', [
                'profile' => $profile,
                'savedBaskets' => $savedBaskets,
                'isAdmin' => isset($customer['role']) && in_array($customer['role'], ['super_admin', 'site_admin']),
                'jsConfig' => [
                    'profileData' => [
                        'firstName' => $profile['firstName'] ?? '',
                        'lastName' => $profile['lastName'] ?? '',
                        'phone' => $profile['phone'] ?? ''
                    ],
                    'deliveryAddress' => $profile['shippingAddress'] ?? null,
                    'supportedCountries' => $this->getSupportedCountries()
                ]
            ], 'My Account - OxWinches');
            
        } catch (MiaException $e) {
            error_log("Account MiaException: " . $e->getMessage());
            $this->showError("Failed to load account: " . $e->getMessage());
        }
    }

    public function showOrders(): void
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
            } else {
                // Regular customers can view their orders
                $ordersResponse = $this->client->orders->getOrders();
                $orders = $ordersResponse['items'] ?? [];
            }
            
            $this->renderLayout('orders', [
                'orders' => $orders,
                'isAdmin' => isset($customer['role']) && in_array($customer['role'], ['super_admin', 'site_admin'])
            ], 'Order History - OxWinches');
            
        } catch (MiaException $e) {
            error_log("Orders MiaException: " . $e->getMessage());
            $this->showError("Failed to load orders: " . $e->getMessage());
        }
    }

    public function showOrderDetails(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login');
            return;
        }
        
        $orderId = $_GET['id'] ?? '';
        if (!$orderId) {
            $this->show404();
            return;
        }
        
        try {
            $order = $this->client->orders->getOrder($orderId);
            
            $this->renderLayout('order-details', [
                'order' => $order
            ], 'Order #' . ($order['orderNumber'] ?? $orderId) . ' - OxWinches');
            
        } catch (\Exception $e) {
            error_log("Order details error: " . $e->getMessage());
            if (strpos($e->getMessage(), '404') !== false) {
                $this->show404();
            } else {
                $this->showError("Failed to load order details: " . $e->getMessage());
            }
        }
    }

    public function apiUpdateProfile(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updateData = [];
            
            // Update firstName if provided
            if (isset($data['firstName'])) {
                $updateData['firstName'] = trim($data['firstName']);
            }
            
            // Update lastName if provided
            if (isset($data['lastName'])) {
                $updateData['lastName'] = trim($data['lastName']);
            }
            
            // Update phone if provided
            if (isset($data['phone'])) {
                $updateData['phone'] = trim($data['phone']);
            }
            
            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No data to update']);
                return;
            }
            
            $result = $this->client->customer->updateProfile($updateData);
            
            // Update session with new customer data
            if (isset($_SESSION['customer'])) {
                $_SESSION['customer'] = array_merge($_SESSION['customer'], $updateData);
            }
            
            echo json_encode(['success' => true, 'profile' => $result]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiUpdateShippingAddress(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return;
        }
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate shipping address if provided
            if (isset($data['shippingAddress'])) {
                $address = $data['shippingAddress'];
                
                // Validate required fields
                $requiredFields = ['line1', 'city', 'postalCode', 'country'];
                foreach ($requiredFields as $field) {
                    if (empty($address[$field])) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false, 
                            'message' => "Shipping address {$field} is required"
                        ]);
                        return;
                    }
                }
                
                // Trim all string values
                $data['shippingAddress'] = [
                    'line1' => trim($address['line1']),
                    'city' => trim($address['city']),
                    'postalCode' => trim($address['postalCode']),
                    'country' => trim($address['country'])
                ];
                
                // Add optional fields if present
                if (!empty($address['name'])) {
                    $data['shippingAddress']['name'] = trim($address['name']);
                }
                if (!empty($address['line2'])) {
                    $data['shippingAddress']['line2'] = trim($address['line2']);
                }
                if (!empty($address['state'])) {
                    $data['shippingAddress']['state'] = trim($address['state']);
                }
                if (!empty($address['phone'])) {
                    $data['shippingAddress']['phone'] = trim($address['phone']);
                }
            }
            
            $result = $this->client->customer->updateProfile($data);
            
            echo json_encode(['success' => true, 'profile' => $result]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
