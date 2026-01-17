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

            $orders = $this->client->orders->getOrders($filters);
            
            $content = $this->view->render('orders', [
                'orders' => $orders['items'] ?? [],
                'total' => $orders['total'] ?? 0,
                'page' => $page,
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
}
