<?php

namespace Marti\Frontend\Controllers;

use Mia\SDK\MiaClient;
use Marti\Frontend\View;
use Marti\Frontend\HtmlResources;
use Marti\Frontend\SettingsCache;

abstract class BaseController
{
    protected $client;
    protected $view;
    protected $config;
    protected $adminPath;
    protected $settingsCache;

    public function __construct(MiaClient $client, View $view, array $config, string $adminPath)
    {
        $this->client = $client;
        $this->view = $view;
        $this->config = $config;
        $this->adminPath = $adminPath;
        
        // Initialize settings cache
        if (!empty($config['site_id'])) {
            $this->settingsCache = new SettingsCache($config['site_id']);
            // Pass settings getter callback to view
            $this->view->setSettingsGetter(function($name) {
                return $this->getSetting($name);
            });
        }
    }

    protected function show404(): void
    {
        http_response_code(404);
        $content = $this->view->render('404');
        echo $this->view->renderLayout('admin-layout', $content, [
            'title' => 'Page Not Found - Admin Panel',
            'user' => $_SESSION['customer'],
            'adminPath' => $this->adminPath
        ]);
    }

    protected function showError(string $message): void
    {
        $content = $this->view->render('error', ['message' => $message]);
        echo $this->view->renderLayout('admin-layout', $content, [
            'title' => 'Error - Admin Panel',
            'user' => $_SESSION['customer'],
            'adminPath' => $this->adminPath
        ]);
    }

    protected function redirect(string $path, ?string $message = null, bool $isError = false): void
    {
        $url = $this->adminPath . $path;
        if ($message) {
            $param = $isError ? 'error' : 'success';
            $url .= (strpos($url, '?') !== false ? '&' : '?') . $param . '=' . urlencode($message);
        }
        header("Location: {$url}");
        exit;
    }

    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function getVatRate(): float
    {
        try {
            // Get VAT rate setting using cached method
            $setting = $this->getSetting('vat_rate');
            
            if (isset($setting['value'])) {
                return (float) $setting['value'];
            }
        } catch (\Exception $e) {
            // If setting doesn't exist or there's an error, use default
        }
        
        return 20.0; // Default UK VAT rate
    }

    /**
     * Get a setting with caching support
     * 
     * @param string $name Setting name
     * @return array|null Setting data ['type' => ..., 'value' => ..., ...] or null if not found
     */
    protected function getSetting(string $name): ?array
    {
        // Try cache first if available
        if ($this->settingsCache) {
            $cached = $this->settingsCache->get($name);
            
            // Cache hit with data
            if ($cached !== null && $cached !== false) {
                return $cached;
            }
            
            // Cache hit with "not found" marker
            if ($cached === false) {
                return null;
            }
        }

        // Cache miss or unavailable - fetch from API
        try {
            $setting = $this->client->siteSettings->getSetting($name);
            
            // Store in cache for future requests
            if ($this->settingsCache && $setting) {
                $this->settingsCache->set($name, $setting);
            }
            
            return $setting;
        } catch (\Exception $e) {
            // Setting not found - cache the "not found" result
            if ($this->settingsCache) {
                $this->settingsCache->setNotFound($name);
            }
            
            error_log("Setting '{$name}' not found: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Set a setting and invalidate cache
     * 
     * @param string $name Setting name
     * @param string $type Setting type (text, secret, image, markdown, json)
     * @param mixed $value Setting value
     * @return void
     * @throws \Exception
     */
    protected function setSetting(string $name, string $type, $value): void
    {
        // Update via API
        $this->client->siteSettings->updateSetting($name, $type, $value);
        
        // Invalidate cache
        if ($this->settingsCache) {
            $this->settingsCache->delete($name);
        }
    }
}
