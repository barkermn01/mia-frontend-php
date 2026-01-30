<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\Exceptions\MiaException;

class AlertsController extends BaseController
{
    public function index(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $severity = $_GET['severity'] ?? null;
            
            $filters = [
                'page' => $page,
                'limit' => 20
            ];
            
            if ($severity) {
                $filters['severity'] = $severity;
            }
            
            // Get alerts
            $alertsResponse = $this->client->admin->getAlerts($filters);
            $alerts = $alertsResponse['items'] ?? [];
            $total = $alertsResponse['total'] ?? 0;
            
            // Get alert count
            $countResponse = $this->client->admin->getAlertCount();
            $unreadCount = $countResponse['unreadCount'] ?? 0;
            
            // Calculate stats
            $criticalCount = 0;
            $todayCount = 0;
            $today = date('Y-m-d');
            
            foreach ($alerts as $alert) {
                if ($alert['severity'] === 'critical') {
                    $criticalCount++;
                }
                if (date('Y-m-d', strtotime($alert['createdAt'])) === $today) {
                    $todayCount++;
                }
            }
            
            $content = $this->view->render('alerts', [
                'alerts' => $alerts,
                'total' => $total,
                'unreadCount' => $unreadCount,
                'criticalCount' => $criticalCount,
                'todayCount' => $todayCount,
                'page' => $page,
                'severity' => $severity,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Payment Alerts - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load alerts: " . $e->getMessage());
        }
    }
    
    public function markAsRead(string $orderId, string $alertId): void
    {
        header('Content-Type: application/json');
        
        try {
            $this->client->admin->markAlertAsRead($orderId, $alertId);
            
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (MiaException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    public function markAllAsRead(): void
    {
        header('Content-Type: application/json');
        
        try {
            // Get all unread alerts and mark them as read
            $alertsResponse = $this->client->admin->getAlerts(['read' => false]);
            $alerts = $alertsResponse['items'] ?? [];
            
            foreach ($alerts as $alert) {
                $this->client->admin->markAlertAsRead($alert['orderId'], $alert['alertId']);
            }
            
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (MiaException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    public function delete(string $orderId, string $alertId): void
    {
        header('Content-Type: application/json');
        
        try {
            $this->client->admin->deleteAlert($orderId, $alertId);
            
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (MiaException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
