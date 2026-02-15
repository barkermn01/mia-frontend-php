<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\Exceptions\MiaException;

class DashboardController extends BaseController
{
    public function index(): void
    {
        try {
            $content = $this->view->render('dashboard', [
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
