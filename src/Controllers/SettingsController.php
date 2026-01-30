<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\Exceptions\MiaException;
use Marti\Frontend\HtmlResources;

class SettingsController extends BaseController
{
    public function index(): void
    {
        try {
            // Get all site settings (no siteId needed, SDK handles it)
            $response = $this->client->siteSettings->getAllSettings();
            
            // Handle both old and new response formats
            if (isset($response['items'])) {
                // New paginated format - convert array of objects to keyed array
                $settings = [];
                foreach ($response['items'] as $item) {
                    $settings[$item['name']] = [
                        'type' => $item['type'],
                        'value' => $item['value'],
                        'createdAt' => $item['createdAt'],
                        'updatedAt' => $item['updatedAt']
                    ];
                }
            } else {
                // Old format - settings is already a keyed array
                $settings = $response['settings'] ?? [];
            }
            
            // Add Toast UI Editor resources for markdown settings
            HtmlResources::getInstance()->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            HtmlResources::getInstance()->addCss('/css/markdown.css');
            HtmlResources::getInstance()->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            $content = $this->view->render('settings', [
                'settings' => $settings,
                'siteId' => $this->config['site_id'],
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Site Settings - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load settings: " . $e->getMessage());
        }
    }

    public function showStripe(): void
    {
        try {
            // Get all site settings
            $response = $this->client->siteSettings->getAllSettings();
            
            // Handle both old and new response formats
            if (isset($response['items'])) {
                $settings = [];
                foreach ($response['items'] as $item) {
                    $settings[$item['name']] = [
                        'type' => $item['type'],
                        'value' => $item['value'],
                        'createdAt' => $item['createdAt'],
                        'updatedAt' => $item['updatedAt']
                    ];
                }
            } else {
                $settings = $response['settings'] ?? [];
            }
            
            $content = $this->view->render('stripe-settings', [
                'settings' => $settings,
                'adminPath' => $this->adminPath
            ]);
            
            echo $this->view->renderLayout('admin-layout', $content, [
                'title' => 'Stripe Configuration - Admin Panel',
                'user' => $_SESSION['customer'],
                'adminPath' => $this->adminPath
            ]);
        } catch (MiaException $e) {
            $this->showError("Failed to load Stripe settings: " . $e->getMessage());
        }
    }

    public function handleUpdate(): void
    {
        try {
            $settingName = $_POST['setting_name'] ?? '';
            $settingType = $_POST['setting_type'] ?? 'text';
            $settingValue = $_POST['setting_value'] ?? '';
            
            if (empty($settingName)) {
                throw new \Exception('Setting name is required');
            }
            
            // Handle JSON type - decode if it's a string
            if ($settingType === 'json' && is_string($settingValue)) {
                $decoded = json_decode($settingValue, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON: ' . json_last_error_msg());
                }
                $settingValue = $decoded;
            }
            
            // Update the setting (no siteId needed, SDK handles it)
            $this->client->siteSettings->updateSetting($settingName, $settingType, $settingValue);
            
            $this->redirect('/settings', "Setting '{$settingName}' updated successfully");
        } catch (\Exception $e) {
            $this->redirect('/settings', $e->getMessage(), true);
        }
    }

    public function handleDelete(): void
    {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $settingName = $data['settingName'] ?? '';
            
            if (empty($settingName)) {
                throw new \Exception('Setting name is required');
            }
            
            // Delete the setting (no siteId needed, SDK handles it)
            $this->client->siteSettings->deleteSetting($settingName);
            
            echo json_encode([
                'success' => true,
                'message' => "Setting '{$settingName}' deleted successfully"
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
