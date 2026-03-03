<?php

namespace Marti\Frontend\Controllers;

class SiteAdminController extends BaseController
{
    public function index(): void
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

    public function showAdd(): void
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

    public function showEdit(): void
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

    public function handleAdd(): void
    {
        try {
            // Force role to site_admin only - prevent any manipulation
            $data = [
                'email' => $_POST['email'] ?? '',
                'firstName' => $_POST['firstName'] ?? '',
                'lastName' => $_POST['lastName'] ?? '',
                'role' => 'site_admin', // Always site_admin, never read from POST
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

            $this->client->admin->createUser($data);
            $this->redirect('/site-admins', 'Site admin created successfully');
        } catch (\Exception $e) {
            $content = $this->view->render('site-admin-form', [
                'admin' => $_POST,
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

    public function handleEdit(): void
    {
        $adminId = $_POST['admin_id'] ?? '';
        
        if (!$adminId) {
            $this->showError("Admin ID is required");
            return;
        }

        try {
            // Force role to site_admin only - prevent any manipulation
            $data = [
                'email' => $_POST['email'] ?? '',
                'firstName' => $_POST['firstName'] ?? '',
                'lastName' => $_POST['lastName'] ?? '',
                'role' => 'site_admin', // Always site_admin, never read from POST
                'status' => $_POST['status'] ?? 'active'
            ];

            // Only include password if provided
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }

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

            $this->client->admin->updateUser($adminId, $data);
            $this->redirect('/site-admins', 'Site admin updated successfully');
        } catch (\Exception $e) {
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
}
