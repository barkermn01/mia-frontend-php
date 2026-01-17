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
            
            $params = [
                'page' => $page,
                'limit' => 20
            ];
            
            if ($search) {
                $params['search'] = $search;
            }

            $customers = $this->client->customer->listCustomers($params);
            
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
            $this->showError("Failed to load customers: " . $e->getMessage());
        }
    }
}
