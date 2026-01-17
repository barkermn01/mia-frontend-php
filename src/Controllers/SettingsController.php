<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\Exceptions\MiaException;
use Marti\Frontend\HtmlResources;

class SettingsController extends BaseController
{
    public function index(): void
    {
        try {
            $siteId = $this->config['site_id'];
            
            // Get all site settings
            $allSettings = $this->client->siteSettings->getAllSettings($siteId);
            $settings = $allSettings['settings'] ?? [];
            
            // Add Toast UI Editor resources for markdown settings
            HtmlResources::getInstance()->addCss('https://uicdn.toast.com/editor/latest/toastui-editor.min.css');
            HtmlResources::getInstance()->addCss('/css/markdown.css');
            HtmlResources::getInstance()->addJsBody('https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js');
            
            $content = $this->view->render('settings', [
                'settings' => $settings,
                'siteId' => $siteId,
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

    public function handleUpdate(): void
    {
        try {
            $siteId = $this->config['site_id'];
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
            
            // Update the setting using the appropriate method
            $this->client->siteSettings->updateSetting($siteId, $settingName, $settingType, $settingValue);
            
            $this->redirect('/settings', "Setting '{$settingName}' updated successfully");
        } catch (\Exception $e) {
            $this->redirect('/settings', $e->getMessage(), true);
        }
    }
}
