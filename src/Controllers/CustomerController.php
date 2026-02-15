<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\Exceptions\MiaException;

class CustomerController extends BaseController
{
    public function index(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $archived = $_GET['archived'] ?? null; // null = all, 'true' = archived only, 'false' = active only
            
            $params = [
                'page' => $page,
                'limit' => 20
            ];
            
            if ($search) {
                $params['search'] = $search;
            }
            
            if ($archived !== null) {
                $params['archived'] = $archived;
            }

            $customers = $this->client->customer->listCustomers($params);
            
            $content = $this->view->render('customers', [
                'customers' => $customers['customers'] ?? [],
                'total' => $customers['pagination']['total'] ?? 0,
                'page' => $page,
                'search' => $search,
                'archived' => $archived,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Customers - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load customers: " . $e->getMessage());
        }
    }

    public function showOrders(): void
    {
        $customerId = $_GET['id'] ?? null;
        
        if (!$customerId) {
            $this->show404();
            return;
        }

        try {
            $page = (int)($_GET['page'] ?? 1);
            $limit = 20;

            // Get customer info
            $customer = $this->client->customer->getCustomer($customerId);

            // Get customer orders
            $ordersData = $this->client->customer->getCustomerOrders($customerId, [
                'page' => $page,
                'limit' => $limit
            ]);

            $content = $this->view->render('customer-orders', [
                'customer' => $customer,
                'orders' => $ordersData['orders'] ?? [],
                'pagination' => $ordersData['pagination'] ?? ['total' => 0, 'page' => 1],
                'page' => $page,
                'adminPath' => $this->adminPath
            ]);

            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Customer Orders - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            error_log("Failed to load customer orders: " . $e->getMessage());
            $this->showError("Failed to load customer orders: " . $e->getMessage());
        }
    }

    public function apiGetCustomer(): void
    {
        $customerId = $_GET['id'] ?? null;

        if (!$customerId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Customer ID required']);
            return;
        }

        try {
            $customer = $this->client->customer->getCustomer($customerId);
            echo json_encode(['success' => true, 'customer' => $customer]);
        } catch (MiaException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiUpdateStatus(): void
    {
        $customerId = $_GET['id'] ?? null;

        if (!$customerId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Customer ID required']);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $status = $data['status'] ?? null;

            if (!$status || !in_array($status, ['active', 'inactive'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Valid status required (active or inactive)']);
                return;
            }

            $result = $this->client->customer->updateCustomerStatus($customerId, $status);
            echo json_encode($result);
        } catch (MiaException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiArchiveCustomer(): void
    {
        $customerId = $_GET['id'] ?? null;

        if (!$customerId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Customer ID required']);
            return;
        }

        try {
            $result = $this->client->customer->deleteCustomer($customerId);
            echo json_encode($result);
        } catch (MiaException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function apiUnarchiveCustomer(): void
    {
        $customerId = $_GET['id'] ?? null;

        if (!$customerId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Customer ID required']);
            return;
        }

        try {
            $result = $this->client->customer->unarchiveCustomer($customerId);
            echo json_encode($result);
        } catch (MiaException $e) {
            $statusCode = 500;
            
            // Check for specific error codes
            if (strpos($e->getMessage(), 'conflict') !== false || strpos($e->getMessage(), 'email address has been registered') !== false) {
                $statusCode = 409;
            } elseif (strpos($e->getMessage(), 'not_found') !== false || strpos($e->getMessage(), 'not found') !== false) {
                $statusCode = 404;
            }
            
            http_response_code($statusCode);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
