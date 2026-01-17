<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\Exceptions\MiaException;

class DashboardController extends BaseController
{
    public function index(): void
    {
        try {
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
}
