<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\Exceptions\MiaException;

class OrderController extends BaseController
{
    public function index(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            
            $filters = [
                'page' => $page,
                'limit' => 20
            ];
            
            // Add search filters if provided
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (!empty($_GET['customerEmail'])) {
                $filters['customerEmail'] = $_GET['customerEmail'];
            }
            if (!empty($_GET['itemName'])) {
                $filters['itemName'] = $_GET['itemName'];
            }
            if (!empty($_GET['shippingCity'])) {
                $filters['shippingCity'] = $_GET['shippingCity'];
            }
            if (!empty($_GET['shippingPostalCode'])) {
                $filters['shippingPostalCode'] = $_GET['shippingPostalCode'];
            }
            if (!empty($_GET['shippingCountry'])) {
                $filters['shippingCountry'] = $_GET['shippingCountry'];
            }

            // Use the new getAdminOrders method for admin panel
            $orders = $this->client->orders->getAdminOrders($filters);
            
            $content = $this->view->render('orders', [
                'orders' => $orders['items'] ?? [],
                'total' => $orders['total'] ?? 0,
                'page' => $page,
                'filters' => $filters,
                'adminPath' => $this->adminPath
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

    public function showDetails(string $orderId): void
    {
        try {
            // Use the new getAdminOrder method for admin panel (no customer restriction)
            $order = $this->client->orders->getAdminOrder($orderId);
            
            $content = $this->view->render('order-details', [
                'order' => $order,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Order #' . ($order['orderNumber'] ?? $orderId) . ' - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load order details: " . $e->getMessage());
        }
    }
    
    public function handleProcess(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = $data['orderId'] ?? '';
            
            if (empty($orderId)) {
                throw new \Exception('Order ID is required');
            }
            
            // Process the order (mark as processing)
            // SDK expects array with 'status' key
            $result = $this->client->orders->updateOrderStatus($orderId, [
                'status' => 'processing'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Order marked as processing'
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function handleCancel(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = $data['orderId'] ?? '';
            
            if (empty($orderId)) {
                throw new \Exception('Order ID is required');
            }
            
            // Cancel the order
            // SDK expects array with 'status' key
            $result = $this->client->orders->updateOrderStatus($orderId, [
                'status' => 'cancelled'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Order cancelled'
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function handleShip(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = $data['orderId'] ?? '';
            $trackingNumber = $data['trackingNumber'] ?? '';
            $trackingUrl = $data['trackingUrl'] ?? '';
            $carrier = $data['carrier'] ?? '';
            
            if (empty($orderId)) {
                throw new \Exception('Order ID is required');
            }
            
            if (empty($trackingNumber)) {
                throw new \Exception('Tracking number is required');
            }
            
            if (empty($trackingUrl)) {
                throw new \Exception('Tracking URL is required');
            }
            
            // Validate URL format
            if (!filter_var($trackingUrl, FILTER_VALIDATE_URL)) {
                throw new \Exception('Invalid tracking URL format');
            }
            
            // Build shipping object according to new API requirements
            $updateData = [
                'status' => 'shipped',
                'shipping' => [
                    'trackingNumber' => $trackingNumber,
                    'trackingUrl' => $trackingUrl
                ]
            ];
            
            // Add optional carrier
            if (!empty($carrier)) {
                $updateData['shipping']['carrier'] = $carrier;
            }
            
            $result = $this->client->orders->updateOrderStatus($orderId, $updateData);
            
            echo json_encode([
                'success' => true,
                'message' => 'Order marked as shipped. Customer will receive an email notification.'
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function handleComplete(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = $data['orderId'] ?? '';
            
            if (empty($orderId)) {
                throw new \Exception('Order ID is required');
            }
            
            // Mark order as completed
            $result = $this->client->orders->updateOrderStatus($orderId, [
                'status' => 'completed'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Order marked as completed'
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function handleUpdateStatus(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = $data['orderId'] ?? '';
            $status = $data['status'] ?? '';
            
            if (empty($orderId)) {
                throw new \Exception('Order ID is required');
            }
            
            if (empty($status)) {
                throw new \Exception('Status is required');
            }
            
            // Validate status
            $validStatuses = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
            if (!in_array($status, $validStatuses)) {
                throw new \Exception('Invalid status');
            }
            
            // Update order status
            $result = $this->client->orders->updateOrderStatus($orderId, [
                'status' => $status
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Order status updated to ' . $status
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function handleRefund(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = $data['orderId'] ?? '';
            $reason = $data['reason'] ?? 'Order cancelled by admin';
            
            if (empty($orderId)) {
                throw new \Exception('Order ID is required');
            }
            
            // Cancel the order - backend will automatically process full refund
            $result = $this->client->orders->updateOrderStatus($orderId, [
                'status' => 'cancelled',
                'reason' => $reason
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Order cancelled and refunded successfully'
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function handlePartialRefund(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = $data['orderId'] ?? '';
            $amount = $data['amount'] ?? 0;
            $reason = $data['reason'] ?? 'Partial refund processed by admin';
            
            if (empty($orderId)) {
                throw new \Exception('Order ID is required');
            }
            
            if (empty($amount) || $amount <= 0) {
                throw new \Exception('Refund amount must be greater than zero');
            }
            
            // Process partial refund using createRefund
            $result = $this->client->orders->createRefund($orderId, [
                'amount' => (int)$amount,
                'reason' => $reason
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Partial refund processed successfully'
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
